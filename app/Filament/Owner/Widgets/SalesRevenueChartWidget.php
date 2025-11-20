<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Payment;
use App\Services\GlobalFilterService;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class SalesRevenueChartWidget extends ChartWidget
{
    /**
     * UPDATED: Now using GlobalFilterService for unified multi-store dashboard
     * 
     * Chart automatically refreshes when global filter changes
     * Shows combined data for all selected stores
     */

    protected ?string $heading = 'Grafik Total Pendapatan';

    protected int | string | array $columnSpan = 'full';

    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        // Trigger refresh when global filter changes
        $this->updateChartData();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Get current filter values from global filter
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
        $dateRange = $globalFilter->getCurrentDateRange();
        $summary = $globalFilter->getFilterSummary();
        
        if (empty($storeIds)) {
            return [
                'datasets' => [[ 'label' => 'Pendapatan', 'data' => [] ]],
                'labels' => [],
            ];
        }

        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $labels = [];
        $data = [];

        // Determine granularity based on date range
        $diffInDays = $start->diffInDays($end);

        if ($diffInDays <= 1) {
            // Per jam untuk 1 hari
            for ($h = 0; $h < 24; $h++) {
                $hourStart = $start->copy()->setTime($h, 0, 0);
                $hourEnd = $start->copy()->setTime($h, 59, 59);

                $sum = Payment::whereIn('store_id', $storeIds)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$hourStart, $hourEnd])
                    ->sum('amount');

                $labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
                $data[] = (float) $sum;
            }
        } else {
            // Per hari untuk multi-day periods
            $period = \Carbon\CarbonPeriod::create($start, '1 day', $end);
            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $sum = Payment::whereIn('store_id', $storeIds)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->sum('amount');

                $labels[] = $date->format('d M');
                $data[] = (float) $sum;
            }
        }

        // Update heading with filter info
        $this->heading = 'Grafik Total Pendapatan - ' . $summary['store'] . ' (' . $summary['date_preset_label'] . ')';

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan',
                    'data' => $data,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
            ],
            'labels' => $labels,
        ];
    }
}


