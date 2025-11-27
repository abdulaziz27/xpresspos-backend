<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SalesAndProductsPerDayChart extends ChartWidget
{
    protected ?string $heading = 'Penjualan Per Produk';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static bool $isLazy = false;

    #[On('sales-report-filter-updated')]
    public function refreshChart(): void
    {
        $this->cachedData = null;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $filters = Session::get('local_filter.salesreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            return [
                'datasets' => [
                    ['label' => 'Jumlah Penjualan', 'data' => []],
                ],
                'labels' => [],
            ];
        }

        $storeIds = [];
        if ($storeId) {
            $storeIds = [$storeId];
        } else {
            $storeIds = Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        }

        if (empty($storeIds)) {
            return [
                'datasets' => [
                    ['label' => 'Jumlah Penjualan', 'data' => []],
                ],
                'labels' => [],
            ];
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);
        $start = $range['start'];
        $end = $range['end'];

        // Get products with their total quantity sold
        $products = OrderItem::withoutGlobalScopes()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.store_id', $storeIds)
            ->where('orders.status', 'completed')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('orders.completed_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('orders.completed_at')
                         ->whereBetween('orders.created_at', [$start, $end]);
                  });
            })
            ->select(
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_quantity')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(10) // Top 10 products
            ->get();

        $labels = [];
        $salesData = [];
        $backgroundColors = [];
        $borderColors = [];

        // Generate gradasi biru dari tua ke muda untuk 10 produk
        // Biru tua: rgb(30, 64, 175) -> Biru muda: rgb(191, 219, 254)
        $blueStart = ['r' => 30, 'g' => 64, 'b' => 175];   // Biru tua
        $blueEnd = ['r' => 191, 'g' => 219, 'b' => 254];   // Biru muda
        
        $productCount = min($products->count(), 10);
        
        foreach ($products->take(10) as $index => $product) {
            $labels[] = $product->name;
            $salesData[] = (int) $product->total_quantity;
            
            // Hitung gradasi warna biru
            $ratio = $productCount > 1 ? $index / ($productCount - 1) : 0;
            $r = round($blueStart['r'] + ($blueEnd['r'] - $blueStart['r']) * $ratio);
            $g = round($blueStart['g'] + ($blueEnd['g'] - $blueStart['g']) * $ratio);
            $b = round($blueStart['b'] + ($blueEnd['b'] - $blueStart['b']) * $ratio);
            
            $backgroundColors[] = "rgba({$r}, {$g}, {$b}, 0.8)";
            $borderColors[] = "rgb({$r}, {$g}, {$b})";
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Penjualan',
                    'data' => $salesData,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => $borderColors,
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Total Penjualan (Unit)',
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                        'precision' => 0,
                    ],
                ],
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Produk',
                    ],
                ],
            ],
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

    protected function getDateRange(string $preset, ?string $dateStart, ?string $dateEnd): array
    {
        if ($preset === 'custom' && $dateStart && $dateEnd) {
            return [
                'start' => Carbon::parse($dateStart)->startOfDay(),
                'end' => Carbon::parse($dateEnd)->endOfDay(),
            ];
        }

        $globalService = app(\App\Services\GlobalFilterService::class);
        return $globalService->getDateRangeForPreset($preset);
    }
}

