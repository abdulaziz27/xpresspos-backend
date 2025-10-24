<?php

namespace App\Filament\Owner\Widgets;

use App\Services\FnBAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdvancedAnalyticsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [];
        }

        $analyticsService = app(FnBAnalyticsService::class);
        $analytics = $analyticsService->getSalesAnalytics('today');
        $profitAnalysis = $analyticsService->getProfitAnalysis('today');

        $totalProfit = collect($profitAnalysis)->sum('profit');
        $avgMargin = collect($profitAnalysis)->avg('margin_percent') ?? 0;
        $topProduct = collect($profitAnalysis)->first();

        return [
            Stat::make('Today\'s Profit', 'Rp ' . number_format($totalProfit, 0, ',', '.'))
                ->description('Total profit from all products')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),

            Stat::make('Average Margin', number_format($avgMargin, 1) . '%')
                ->description('Average profit margin across products')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($avgMargin >= 30 ? 'success' : ($avgMargin >= 20 ? 'warning' : 'danger')),

            Stat::make('Top Selling Product', $topProduct['product_name'] ?? 'No sales')
                ->description($topProduct ? 'Sold: ' . $topProduct['quantity_sold'] . ' units' : 'No products sold today')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('info'),

            Stat::make('Items Sold', number_format($analytics['summary']['total_items_sold'] ?? 0))
                ->description('Total items sold today')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),
        ];
    }
}