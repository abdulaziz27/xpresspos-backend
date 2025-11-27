<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\InventoryMovement;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class MaterialUsageChart extends ChartWidget
{
    protected ?string $heading = 'Grafik Jumlah Penggunaan Bahan Baku';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    #[On('inventory-report-filter-updated')]
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
        $filters = Session::get('local_filter.inventoryreport.filters', $this->getDefaultFilters());
        
        $tenantId = $filters['tenant_id'] ?? null;
        $storeId = $filters['store_id'] ?? null;
        $datePreset = $filters['date_preset'] ?? 'this_month';
        $dateStart = $filters['date_start'] ?? null;
        $dateEnd = $filters['date_end'] ?? null;

        if (!$tenantId) {
            return [
                'datasets' => [[ 'data' => [] ]],
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
                'datasets' => [[ 'data' => [] ]],
                'labels' => [],
            ];
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);

        // Get material usage (stock out movements)
        $usage = InventoryMovement::query()
            ->whereIn('store_id', $storeIds)
            ->whereHas('inventoryItem', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->whereBetween('created_at', [$range['start'], $range['end']])
            ->whereIn('type', [
                InventoryMovement::TYPE_SALE,
                InventoryMovement::TYPE_ADJUSTMENT_OUT,
                InventoryMovement::TYPE_TRANSFER_OUT,
                InventoryMovement::TYPE_WASTE
            ])
            ->select('inventory_item_id', DB::raw('SUM(quantity) as total_usage'))
            ->groupBy('inventory_item_id')
            ->with('inventoryItem')
            ->orderByDesc('total_usage')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($usage as $item) {
            $labels[] = $item->inventoryItem->name ?? 'Unknown';
            $data[] = (float) $item->total_usage;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Penggunaan',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 1,
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
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
}

