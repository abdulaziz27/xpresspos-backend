<?php

namespace App\Filament\Owner\Widgets;

use App\Services\FnBAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitAnalysisWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [];
        }

        try {
            $analyticsService = app(FnBAnalyticsService::class);
            $profitAnalysis = $analyticsService->getProfitAnalysis('today');

            if (empty($profitAnalysis)) {
                return [
                    Stat::make('No Sales Data', 'No products sold today')
                        ->description('Start selling to see profit analysis')
                        ->descriptionIcon('heroicon-m-information-circle')
                        ->color('gray'),
                ];
            }

            $topProduct = $profitAnalysis[0] ?? null;
            $totalProfit = collect($profitAnalysis)->sum('profit');
            $avgMargin = collect($profitAnalysis)->avg('margin_percent') ?? 0;

            return [
                Stat::make('Top Product', $topProduct['product_name'] ?? 'N/A')
                    ->description($topProduct ? 'Profit: Rp ' . number_format($topProduct['profit'], 0, ',', '.') : 'No data')
                    ->descriptionIcon('heroicon-m-trophy')
                    ->color('success'),

                Stat::make('Total Profit Today', 'Rp ' . number_format($totalProfit, 0, ',', '.'))
                    ->description('From ' . count($profitAnalysis) . ' products')
                    ->descriptionIcon('heroicon-m-currency-dollar')
                    ->color($totalProfit > 0 ? 'success' : 'gray'),

                Stat::make('Average Margin', number_format($avgMargin, 1) . '%')
                    ->description('Across all products')
                    ->descriptionIcon('heroicon-m-chart-pie')
                    ->color($avgMargin >= 30 ? 'success' : ($avgMargin >= 20 ? 'warning' : 'danger')),
            ];

        } catch (\Exception $e) {
            return [
                Stat::make('Error', 'Unable to load data')
                    ->description('Please try again later')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('danger'),
            ];
        }
    }
}