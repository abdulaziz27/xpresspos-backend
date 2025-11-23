<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\OrderItemDiscount;
use App\Models\CogsHistory;
use App\Models\StockLevel;
use App\Models\InventoryItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Product;
use App\Services\Ai\Clients\AiClientInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiAnalyticsService
{
    public function __construct(
        protected AiClientInterface $client
    ) {
    }

    /**
     * Analyze data and generate AI response.
     *
     * @param string $question
     * @param string|null $storeId
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @param User|null $user
     * @return string
     */
    public function analyze(
        string $question,
        ?string $storeId,
        ?Carbon $from,
        ?Carbon $to,
        ?User $user = null
    ): string {
        $user = $user ?? auth()->user();

        if (!$user) {
            return 'Error: Pengguna tidak terautentikasi.';
        }

        $tenantId = $user->currentTenant()?->id;

        if (!$tenantId) {
            return 'Error: Tidak dapat menentukan tenant aktif.';
        }

        // Resolve date range
        [$from, $to] = $this->resolveDateRange($from, $to);

        // Validate store belongs to tenant and get store name
        $storeName = null;
        if ($storeId) {
            $store = Store::where('id', $storeId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$store) {
                return 'Error: Toko tidak ditemukan atau tidak memiliki akses.';
            }
            
            $storeName = $store->name;
        }

        // Build context
        $context = $this->buildContext($tenantId, $storeId, $storeName, $from, $to);

        // Build prompt
        $prompt = $this->buildPrompt($context, $question);

        // Get AI response
        return $this->client->ask($prompt);
    }

    /**
     * Resolve date range with defaults.
     *
     * @param Carbon|null $from
     * @param Carbon|null $to
     * @return array [Carbon $from, Carbon $to]
     */
    protected function resolveDateRange(?Carbon $from, ?Carbon $to): array
    {
        $to = $to ?? today()->endOfDay();
        $from = $from ?? today()->subDays(6)->startOfDay();

        return [$from, $to];
    }

    /**
     * Build context data from database.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @param string|null $storeName
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    protected function buildContext(string $tenantId, ?string $storeId, ?string $storeName, Carbon $from, Carbon $to): array
    {
        $context = [
            'meta' => [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'store_name' => $storeName,
                'date_range' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
            ],
            'sales_summary' => $this->getSalesSummary($tenantId, $storeId, $from, $to),
            'sales_by_day' => $this->getSalesByDay($tenantId, $storeId, $from, $to),
            'top_products' => $this->getTopProducts($tenantId, $storeId, $from, $to),
            'cogs_summary' => $this->getCogsSummary($tenantId, $storeId, $from, $to),
            'low_stock_items' => $this->getLowStockItems($tenantId, $storeId),
            'stores_info' => $this->getStoresInfo($tenantId),
            'staff_info' => $this->getStaffInfo($tenantId, $storeId),
        ];

        return $context;
    }

    /**
     * Get sales summary.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    protected function getSalesSummary(string $tenantId, ?string $storeId, Carbon $from, Carbon $to): array
    {
        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        if ($storeId) {
            $ordersQuery->where('store_id', $storeId);
        }

        // Apply date filter (completed_at or created_at)
        $ordersQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('completed_at', [$from, $to])
                ->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('completed_at')
                        ->whereBetween('created_at', [$from, $to]);
                });
        });

        // Total sales (subtotal)
        $totalSales = (float) (clone $ordersQuery)->sum('subtotal');
        $totalSales = round($totalSales, 2);

        // Total orders
        $totalOrders = (clone $ordersQuery)->count();

        // Average order value
        $averageOrderValue = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        // Total discounts (order-level + item-level)
        $orderIds = (clone $ordersQuery)->pluck('id');

        $orderDiscounts = OrderDiscount::query()
            ->whereIn('order_id', $orderIds)
            ->sum('discount_amount');

        $itemDiscounts = OrderItemDiscount::query()
            ->whereHas('orderItem', function ($q) use ($orderIds) {
                $q->whereIn('order_id', $orderIds);
            })
            ->sum('discount_amount');

        $totalDiscounts = round((float) $orderDiscounts + (float) $itemDiscounts, 2);

        // Total tax
        $totalTax = round((float) (clone $ordersQuery)->sum('tax_amount'), 2);

        // Total service charge
        $totalServiceCharge = round((float) (clone $ordersQuery)->sum('service_charge'), 2);

        return [
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'average_order_value' => $averageOrderValue,
            'total_discounts' => $totalDiscounts,
            'total_tax' => $totalTax,
            'total_service_charge' => $totalServiceCharge,
        ];
    }

    /**
     * Get sales by day.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    protected function getSalesByDay(string $tenantId, ?string $storeId, Carbon $from, Carbon $to): array
    {
        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        if ($storeId) {
            $ordersQuery->where('store_id', $storeId);
        }

        // Apply date filter
        $ordersQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('completed_at', [$from, $to])
                ->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('completed_at')
                        ->whereBetween('created_at', [$from, $to]);
                });
        });

        // Group by day
        $results = (clone $ordersQuery)
            ->select(
                DB::raw("DATE(COALESCE(completed_at, created_at)) as date"),
                DB::raw("SUM(subtotal) as total_sales"),
                DB::raw("COUNT(*) as order_count")
            )
            ->groupBy(DB::raw("DATE(COALESCE(completed_at, created_at))"))
            ->orderBy('date')
            ->limit(30)
            ->get();

        return $results->map(function ($row) {
            return [
                'date' => $row->date,
                'total_sales' => round((float) $row->total_sales, 2),
                'order_count' => (int) $row->order_count,
            ];
        })->toArray();
    }

    /**
     * Get top products.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    protected function getTopProducts(string $tenantId, ?string $storeId, Carbon $from, Carbon $to): array
    {
        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        if ($storeId) {
            $ordersQuery->where('store_id', $storeId);
        }

        // Apply date filter
        $ordersQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('completed_at', [$from, $to])
                ->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('completed_at')
                        ->whereBetween('created_at', [$from, $to]);
                });
        });

        $orderIds = (clone $ordersQuery)->pluck('id');

        // Get top products by revenue
        $topProducts = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('products.tenant_id', $tenantId)
            ->select(
                'order_items.product_id',
                'products.name',
                DB::raw("SUM(order_items.quantity) as qty_sold"),
                DB::raw("SUM(order_items.total_price) as revenue")
            )
            ->groupBy('order_items.product_id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return $topProducts->map(function ($row) {
            return [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'qty_sold' => round((float) $row->qty_sold, 2),
                'revenue' => round((float) $row->revenue, 2),
            ];
        })->toArray();
    }

    /**
     * Get COGS summary.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @param Carbon $from
     * @param Carbon $to
     * @return array
     */
    protected function getCogsSummary(string $tenantId, ?string $storeId, Carbon $from, Carbon $to): array
    {
        // Get orders in date range
        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        if ($storeId) {
            $ordersQuery->where('store_id', $storeId);
        }

        // Apply date filter
        $ordersQuery->where(function ($q) use ($from, $to) {
            $q->whereBetween('completed_at', [$from, $to])
                ->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('completed_at')
                        ->whereBetween('created_at', [$from, $to]);
                });
        });

        $orderIds = (clone $ordersQuery)->pluck('id');

        // Get total COGS from cogs_history
        $totalCogsQuery = CogsHistory::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('order_id')
            ->whereIn('order_id', $orderIds);

        if ($storeId) {
            $totalCogsQuery->where('store_id', $storeId);
        }

        $totalCogs = round((float) $totalCogsQuery->sum('total_cogs'), 2);

        // Get total sales for gross profit calculation
        $totalSales = round((float) (clone $ordersQuery)->sum('subtotal'), 2);

        // Gross profit
        $grossProfit = round($totalSales - $totalCogs, 2);

        // Gross margin percentage
        $grossMarginPct = $totalSales > 0 ? round(($grossProfit / $totalSales) * 100, 2) : null;

        return [
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'gross_margin_pct' => $grossMarginPct,
        ];
    }

    /**
     * Get low stock items.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @return array
     */
    protected function getLowStockItems(string $tenantId, ?string $storeId): array
    {
        $stockLevelQuery = StockLevel::query()
            ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
            ->join('stores', 'stock_levels.store_id', '=', 'stores.id')
            ->where('stock_levels.tenant_id', $tenantId)
            ->where('inventory_items.tenant_id', $tenantId)
            ->where('stores.tenant_id', $tenantId)
            ->where('inventory_items.track_stock', true)
            ->whereRaw('stock_levels.current_stock <= stock_levels.min_stock_level')
            ->where('stock_levels.min_stock_level', '>', 0);

        if ($storeId) {
            $stockLevelQuery->where('stock_levels.store_id', $storeId);
        }

        $lowStockItems = $stockLevelQuery
            ->select(
                'stock_levels.inventory_item_id',
                'inventory_items.name',
                'stores.name as store_name',
                'stock_levels.current_stock',
                'stock_levels.min_stock_level'
            )
            ->orderBy('stock_levels.current_stock')
            ->limit(10)
            ->get();

        return $lowStockItems->map(function ($row) {
            return [
                'inventory_item_id' => $row->inventory_item_id,
                'name' => $row->name,
                'store_name' => $row->store_name,
                'current_stock' => round((float) $row->current_stock, 3),
                'min_stock_level' => round((float) $row->min_stock_level, 3),
            ];
        })->toArray();
    }

    /**
     * Get stores information.
     *
     * @param string $tenantId
     * @return array
     */
    protected function getStoresInfo(string $tenantId): array
    {
        $stores = Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'status', 'created_at']);

        return $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'status' => $store->status,
            ];
        })->toArray();
    }

    /**
     * Get staff information.
     *
     * @param string $tenantId
     * @param string|null $storeId
     * @return array
     */
    protected function getStaffInfo(string $tenantId, ?string $storeId): array
    {
        // Get all stores for tenant if storeId is null
        $storeIds = $storeId 
            ? [$storeId] 
            : Store::where('tenant_id', $tenantId)->pluck('id')->toArray();

        // Get staff assignments
        $assignments = \App\Models\StoreUserAssignment::query()
            ->whereIn('store_id', $storeIds)
            ->with(['user', 'store'])
            ->get();

        // Group by store
        $staffByStore = [];
        $totalStaff = 0;
        $uniqueStaffIds = [];

        foreach ($assignments as $assignment) {
            $storeName = $assignment->store->name ?? 'Unknown';
            $staffId = $assignment->user_id;

            if (!isset($staffByStore[$storeName])) {
                $staffByStore[$storeName] = [];
            }

            if (!in_array($staffId, $uniqueStaffIds)) {
                $uniqueStaffIds[] = $staffId;
                $totalStaff++;
            }

            $role = $assignment->assignment_role;
            $roleValue = $role instanceof \BackedEnum ? $role->value : ($role ?? 'staff');
            
            $staffByStore[$storeName][] = [
                'user_id' => $staffId,
                'user_name' => $assignment->user->name ?? 'Unknown',
                'role' => $roleValue,
                'is_primary' => $assignment->is_primary ?? false,
            ];
        }

        return [
            'total_staff' => $totalStaff,
            'total_stores' => count($storeIds),
            'staff_by_store' => $staffByStore,
        ];
    }

    /**
     * Build prompt for AI.
     *
     * @param array $context
     * @param string $question
     * @return string
     */
    protected function buildPrompt(array $context, string $question): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Build context description
        $dateFrom = $context['meta']['date_range']['from'];
        $dateTo = $context['meta']['date_range']['to'];
        $storeId = $context['meta']['store_id'];
        $storeName = $context['meta']['store_name'] ?? null;
        $storeInfo = $storeName ? "untuk toko: {$storeName}" : ($storeId ? "untuk toko spesifik (ID: {$storeId})" : "untuk semua toko");
        
        // Determine date range description
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();
        
        $dateDescription = "Periode: {$dateFrom} sampai {$dateTo}";
        if ($dateFrom === $dateTo) {
            if ($dateFrom === $today) {
                $dateDescription = "Periode: HARI INI ({$dateFrom})";
            } elseif ($dateFrom === $yesterday) {
                $dateDescription = "Periode: KEMARIN ({$dateFrom})";
            } else {
                $dateDescription = "Periode: {$dateFrom} (hari spesifik)";
            }
        }

        return <<<PROMPT
Kamu adalah asisten data analyst untuk sebuah bisnis F&B. 

KONTEKS DATA:
- {$dateDescription}
- Scope: {$storeInfo}
- Data yang tersedia sudah ter-filter berdasarkan periode dan toko di atas

DATA YANG TERSEDIA:
1. sales_summary: Total penjualan, jumlah order, rata-rata order, diskon, pajak, service charge
2. sales_by_day: Data penjualan harian (maksimal 30 hari terakhir)
3. top_products: 10 produk terlaris dengan quantity dan revenue
4. cogs_summary: Total COGS, gross profit, dan gross margin percentage
5. low_stock_items: Item dengan stok di bawah minimum (maksimal 10 item)
6. stores_info: Daftar semua toko milik tenant (id, name, code, status)
7. staff_info: Informasi staff per toko (total staff, staff by store, role)

PENTING:
- Jika user bertanya "hari ini", itu mengacu pada tanggal {$dateTo} (tanggal akhir dari periode yang dipilih)
- Jika user bertanya "kemarin", itu mengacu pada tanggal sebelum {$dateFrom}
- Data sales_by_day hanya menampilkan hari-hari yang ada transaksi
- Jika store_id null, berarti data mencakup semua toko milik tenant
- Jika store_id ada, berarti data hanya untuk toko tersebut
- Data hanya mencakup order dengan status 'completed'

INSTRUKSI:
1. Jawab dengan bahasa Indonesia yang singkat, to the point, dan mudah dimengerti pemilik bisnis
2. Gunakan data yang tersedia di JSON. Jangan mengarang data di luar JSON
3. Jika data tidak tersedia, jelaskan dengan jelas keterbatasannya
4. Untuk pertanyaan tentang "hari ini", gunakan tanggal {$dateTo}
5. Jika sales_by_day kosong atau tidak ada data untuk tanggal tertentu, jelaskan bahwa tidak ada transaksi pada periode tersebut
6. Untuk pertanyaan tentang toko:
   - Gunakan data stores_info untuk menjawab pertanyaan tentang daftar toko, jumlah toko, dll
   - Jika store_id null, jelaskan bahwa data mencakup semua toko dan sebutkan jumlah toko dari stores_info
   - Jika store_id ada, sebutkan nama toko dari store_name
7. Untuk pertanyaan tentang staff:
   - Gunakan data staff_info untuk menjawab pertanyaan tentang jumlah staff, staff per toko, dll
   - Jika ditanya tentang kompetensi staff, jelaskan bahwa data kompetensi tidak tersedia, hanya data assignment dan role
8. Untuk pertanyaan tentang periode yang lebih luas (bulan ini, tahun ini):
   - Jika data sales_by_day tidak mencakup periode tersebut, jelaskan bahwa data hanya tersedia untuk periode yang dipilih
   - Sarankan user untuk mengubah filter date range jika ingin melihat periode yang lebih luas
9. Gunakan semua data yang tersedia secara maksimal. Jika ada data yang relevan, gunakan untuk menjawab pertanyaan

Berikut data dalam JSON:

{$contextJson}

Pertanyaan user:
"{$question}"
PROMPT;
    }
}

