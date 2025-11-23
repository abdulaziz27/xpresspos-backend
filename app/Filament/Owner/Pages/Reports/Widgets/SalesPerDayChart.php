<?php

namespace App\Filament\Owner\Pages\Reports\Widgets;

use App\Models\Payment;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SalesPerDayChart extends ChartWidget
{
    protected ?string $heading = 'Penjualan Per Hari';

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
                'datasets' => [[ 'label' => 'Omzet', 'data' => [] ]],
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
                'datasets' => [[ 'label' => 'Omzet', 'data' => [] ]],
                'labels' => [],
            ];
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);
        $start = $range['start'];
        $end = $range['end'];

        $labels = [];
        $data = [];

        // Generate all dates in range
        $period = \Carbon\CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->endOfDay());
        
        foreach ($period as $date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            // Query payments with effective_paid_at - SUM amount per hari
            $sum = Payment::withoutGlobalScopes()
                ->whereIn('store_id', $storeIds)
                ->where('status', 'completed')
                ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$dayStart, $dayEnd])
                ->sum(DB::raw('CASE WHEN received_amount > 0 THEN received_amount ELSE amount END'));

            $labels[] = $date->format('d M');
            $data[] = (float) $sum;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Omzet',
                    'data' => $data,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
            ],
            'labels' => $labels,
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

