<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItemDiscount;
use App\Models\CogsHistory;
use App\Models\Expense;
use App\Models\Store;
use App\Support\Currency;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class ProfitLossSummaryCard extends Widget
{
    protected static ?int $sort = 11;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.owner.pages.reports.widgets.profit-loss-summary-card';

    public array $profitLossData = [];

    #[On('profit-loss-filter-updated')]
    public function refreshData(): void
    {
        $this->loadData();
    }

    public function mount(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $filters = Session::get('local_filter.profitlossreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            $this->profitLossData = $this->getEmptyData();
            return;
        }

        $storeIds = $this->getStoreIds($tenantId, $storeId);
        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        if (empty($storeIds)) {
            $this->profitLossData = $this->getEmptyData();
            return;
        }

        // Build base orders query
        $ordersQuery = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed');

        // Apply date filter for orders
        if ($range['start'] && $range['end']) {
            $ordersQuery->where(function ($q) use ($range) {
                $q->whereBetween('completed_at', [$range['start'], $range['end']])
                  ->orWhere(function ($q2) use ($range) {
                      $q2->whereNull('completed_at')
                         ->whereBetween('created_at', [$range['start'], $range['end']]);
                  });
            });
        }

        // 1. PENJUALAN KOTOR (Gross Sales)
        $grossSales = (clone $ordersQuery)->sum('subtotal');

        // 2. DISKON
        $orderDiscounts = OrderDiscount::withoutGlobalScopes()
            ->whereHas('order', function ($q) use ($tenantId, $storeIds, $range) {
                $q->withoutGlobalScopes()
                  ->where('tenant_id', $tenantId)
                  ->whereIn('store_id', $storeIds)
                  ->where('status', 'completed');
                
                if ($range['start'] && $range['end']) {
                    $q->where(function ($q2) use ($range) {
                        $q2->whereBetween('completed_at', [$range['start'], $range['end']])
                           ->orWhere(function ($q3) use ($range) {
                               $q3->whereNull('completed_at')
                                  ->whereBetween('created_at', [$range['start'], $range['end']]);
                           });
                    });
                }
            })
            ->sum('discount_amount');

        $itemDiscounts = OrderItemDiscount::withoutGlobalScopes()
            ->join('order_items', 'order_item_discounts.order_item_id', '=', 'order_items.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.status', 'completed')
            ->when($range['start'] && $range['end'], function ($q) use ($range) {
                $q->where(function ($q2) use ($range) {
                    $q2->whereBetween('orders.completed_at', [$range['start'], $range['end']])
                       ->orWhere(function ($q3) use ($range) {
                           $q3->whereNull('orders.completed_at')
                              ->whereBetween('orders.created_at', [$range['start'], $range['end']]);
                       });
                });
            })
            ->sum('order_item_discounts.discount_amount');

        $totalDiscount = $orderDiscounts + $itemDiscounts;

        // 3. PENJUALAN BERSIH (Net Sales)
        $netSales = $grossSales - $totalDiscount;

        // 4. HARGA POKOK PENJUALAN (COGS)
        $orderIds = (clone $ordersQuery)->pluck('id');
        $totalCogs = 0;
        
        if ($orderIds->isNotEmpty()) {
            $totalCogs = CogsHistory::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('store_id', $storeIds)
                ->whereNotNull('order_id')
                ->whereIn('order_id', $orderIds)
                ->sum('total_cogs');
        }

        // 5. LABA KOTOR (Gross Profit)
        $grossProfit = $netSales - $totalCogs;
        $grossProfitMargin = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;

        // 6. BIAYA OPERASIONAL (Operating Expenses)
        $expensesQuery = Expense::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds);

        if ($range['start'] && $range['end']) {
            $expensesQuery->whereBetween('expense_date', [
                $range['start']->toDateString(),
                $range['end']->toDateString()
            ]);
        }

        $totalExpenses = (clone $expensesQuery)->sum('amount');

        // Expenses by category
        $expensesByCategory = (clone $expensesQuery)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->category => (float) $item->total];
            })
            ->toArray();

        // 7. LABA BERSIH (Net Profit)
        $netProfit = $grossProfit - $totalExpenses;
        $netProfitMargin = $netSales > 0 ? ($netProfit / $netSales) * 100 : 0;

        $this->profitLossData = [
            'gross_sales' => (float) $grossSales,
            'total_discount' => (float) $totalDiscount,
            'net_sales' => (float) $netSales,
            'total_cogs' => (float) $totalCogs,
            'gross_profit' => (float) $grossProfit,
            'gross_profit_margin' => round($grossProfitMargin, 2),
            'total_expenses' => (float) $totalExpenses,
            'expenses_by_category' => $expensesByCategory,
            'net_profit' => (float) $netProfit,
            'net_profit_margin' => round($netProfitMargin, 2),
        ];
    }

    protected function getEmptyData(): array
    {
        return [
            'gross_sales' => 0,
            'total_discount' => 0,
            'net_sales' => 0,
            'total_cogs' => 0,
            'gross_profit' => 0,
            'gross_profit_margin' => 0,
            'total_expenses' => 0,
            'expenses_by_category' => [],
            'net_profit' => 0,
            'net_profit_margin' => 0,
        ];
    }

    protected function getDefaultFilters(): array
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;
        $globalService = app(\App\Services\GlobalFilterService::class);
        $preset = 'this_month';
        $range = $globalService->getDateRangeForPreset($preset);

        return [
            'tenant_id' => $tenantId,
            'store_id' => null,
            'date_preset' => $preset,
            'date_start' => $range['start']->toDateString(),
            'date_end' => $range['end']->toDateString(),
        ];
    }

    protected function getStoreIds(?string $tenantId, ?string $storeId): array
    {
        if (!$tenantId) {
            return [];
        }

        if ($storeId) {
            return [$storeId];
        }

        return Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();
    }

    protected function getDateRange(string $preset, ?string $dateStart, ?string $dateEnd): array
    {
        if ($preset === 'custom' && $dateStart && $dateEnd) {
            return [
                'start' => \Carbon\Carbon::parse($dateStart)->startOfDay(),
                'end' => \Carbon\Carbon::parse($dateEnd)->endOfDay(),
            ];
        }

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
    }
}

