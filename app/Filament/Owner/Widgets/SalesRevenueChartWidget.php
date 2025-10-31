<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Payment;
use Filament\Widgets\ChartWidget;

class SalesRevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Grafik Total Pendapatan';

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari ini',
            'this_week' => 'Minggu ini',
            'this_month' => 'Bulan ini',
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [
                'datasets' => [[ 'label' => 'Pendapatan', 'data' => [] ]],
                'labels' => [],
            ];
        }

        $start = now();
        $end = now();

        if ($this->filter === 'this_week') {
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();
        } elseif ($this->filter === 'this_month') {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        } else {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
        }

        $labels = [];
        $data = [];

        if ($this->filter === 'today') {
            // Per jam untuk hari ini
            for ($h = 0; $h < 24; $h++) {
                $hourStart = $start->copy()->setTime($h, 0, 0);
                $hourEnd = $start->copy()->setTime($h, 59, 59);

                $sum = Payment::where('store_id', $storeId)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$hourStart, $hourEnd])
                    ->sum('amount');

                $labels[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
                $data[] = (float) $sum;
            }
        } else {
            // Per hari untuk minggu/bulan ini
            $period = \Carbon\CarbonPeriod::create($start, '1 day', $end);
            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $sum = Payment::where('store_id', $storeId)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->sum('amount');

                $labels[] = $date->format('d M');
                $data[] = (float) $sum;
            }
        }

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


