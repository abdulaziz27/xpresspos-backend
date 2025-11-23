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

class SalesPerHourChart extends ChartWidget
{
    protected ?string $heading = 'Penjualan Per Waktu';

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
                'datasets' => [[ 'label' => 'Omzet per Jam', 'data' => [] ]],
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
                'datasets' => [[ 'label' => 'Omzet per Jam', 'data' => [] ]],
                'labels' => [],
            ];
        }

        $range = $this->getDateRange($datePreset, $dateStart, $dateEnd);
        $start = $range['start'];
        $end = $range['end'];

        $labels = [];
        $data = [];

        // Initialize all hours (0-23) with 0
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $data[$hour] = 0;
        }

        // Query payments grouped by hour
        $payments = Payment::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$start, $end])
            ->select(
                DB::raw('HOUR(COALESCE(paid_at, processed_at, created_at)) as hour'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('hour')
            ->get();

        // Fill data from query results
        foreach ($payments as $payment) {
            $hour = (int) $payment->hour;
            if ($hour >= 0 && $hour < 24) {
                $data[$hour] = (float) $payment->total;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Omzet per Jam',
                    'data' => array_values($data),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.8)',
                    'borderColor' => 'rgb(168, 85, 247)',
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

