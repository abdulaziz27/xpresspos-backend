<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\CogsHistory;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class TopMenuPieWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected ?string $heading = 'Menu Terlaris';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $tenantId = $filters['tenant_id'] ?? null;

        if (! $tenantId || empty($storeIds)) {
            return [
                'datasets' => [[ 'data' => [] ]],
                'labels' => [],
            ];
        }

        $dateRange = $filters['range'];

        $rows = CogsHistory::withoutGlobalScopes()
            ->join('products', 'products.id', '=', 'cogs_history.product_id')
            ->where('cogs_history.tenant_id', $tenantId)
            ->whereIn('cogs_history.store_id', $storeIds)
            ->whereBetween('cogs_history.created_at', [$dateRange['start'], $dateRange['end']])
            ->select([
                DB::raw('products.name as product_name'),
                DB::raw('SUM(cogs_history.quantity_sold) as qty'),
            ])
            ->groupBy('products.name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $labels = [];
        $data = [];

        foreach ($rows as $row) {
            $labels[] = $row->product_name ?? 'Produk';
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


