<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class SalesRevenueChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected ?string $heading = 'Grafik Total Pendapatan';

    protected int | string | array $columnSpan = 'full';

    public function updatedPageFilters(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }

    public function getDescription(): ?string
    {
        return $this->dashboardFilterContextLabel();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $summary = $this->dashboardFilterSummary();
        
        if (empty($storeIds)) {
            return [
                'datasets' => [[ 'label' => 'Pendapatan', 'data' => [] ]],
                'labels' => [],
            ];
        }

        $start = $filters['range']['start'];
        $end = $filters['range']['end'];

        $labels = [];
        $data = [];

        $diffInDays = $start->diffInDays($end);

        if ($diffInDays <= 1) {
            for ($h = 0; $h < 24; $h++) {
                $hourStart = $start->copy()->setTime($h, 0, 0);
                $hourEnd = $start->copy()->setTime($h, 59, 59);

                $sum = Payment::withoutGlobalScopes()
                    ->whereIn('store_id', $storeIds)
                    ->where('status', 'completed')
                    ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$hourStart, $hourEnd])
                    ->sum(DB::raw('CASE WHEN received_amount > 0 THEN received_amount ELSE amount END'));

                $labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
                $data[] = (float) $sum;
            }
        } else {
            $period = \Carbon\CarbonPeriod::create($start, '1 day', $end);
            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $sum = Payment::withoutGlobalScopes()
                    ->whereIn('store_id', $storeIds)
                    ->where('status', 'completed')
                    ->whereBetween(DB::raw('COALESCE(paid_at, processed_at, created_at)'), [$dayStart, $dayEnd])
                    ->sum(DB::raw('CASE WHEN received_amount > 0 THEN received_amount ELSE amount END'));

                $labels[] = $date->format('d M');
                $data[] = (float) $sum;
            }
        }

        $storeLabel = $summary['store'] ?? 'Semua Cabang';
        $this->heading = 'Grafik Total Pendapatan - ' . $storeLabel;

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
