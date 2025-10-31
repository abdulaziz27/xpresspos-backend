<?php

namespace App\Filament\Owner\Widgets;

use App\Models\CogsHistory;
use Filament\Widgets\ChartWidget;

class TopMenuPieWidget extends ChartWidget
{
    protected ?string $heading = 'Menu Terlaris';

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
        return 'pie';
    }

    protected function getData(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [ 'datasets' => [[ 'data' => [] ]], 'labels' => [] ];
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

        $rows = CogsHistory::query()
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('product_id, SUM(quantity_sold) as qty')
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = optional($row->product)->name ?? ('Product #' . $row->product_id);
            $data[] = (int) ($row->qty ?? 0);
        }

        return [
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => [
                    'rgba(59,130,246,.8)','rgba(234,88,12,.8)','rgba(16,185,129,.8)','rgba(139,92,246,.8)','rgba(251,191,36,.8)',
                    'rgba(239,68,68,.8)','rgba(20,184,166,.8)','rgba(99,102,241,.8)','rgba(132,204,22,.8)','rgba(244,63,94,.8)'
                ],
                'borderColor' => [
                    'rgb(59,130,246)','rgb(234,88,12)','rgb(16,185,129)','rgb(139,92,246)','rgb(251,191,36)',
                    'rgb(239,68,68)','rgb(20,184,166)','rgb(99,102,241)','rgb(132,204,22)','rgb(244,63,94)'
                ],
            ]],
            'labels' => $labels,
        ];
    }
}


