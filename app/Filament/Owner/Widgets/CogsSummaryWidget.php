<?php

namespace App\Filament\Owner\Widgets;

use App\Models\CogsHistory;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CogsSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $storeId = auth()->user()?->store_id;
        if (!$storeId) {
            return [
                Stat::make('Today\'s COGS', 'Rp 0')
                    ->description('Cost of goods sold today')
                    ->descriptionIcon('heroicon-m-currency-dollar')
                    ->color('gray'),
                Stat::make('Monthly COGS', 'Rp 0')
                    ->description('0.0% from last month')
                    ->descriptionIcon('heroicon-m-arrow-trending-up')
                    ->color('gray'),
                Stat::make('Recipe Coverage', '0.0%')
                    ->description('0 of 0 products have recipes')
                    ->descriptionIcon('heroicon-m-clipboard-document-list')
                    ->color('gray'),
            ];
        }
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        // Today's COGS
        $todayCogs = CogsHistory::where('store_id', $storeId)
            ->whereDate('created_at', $today)
            ->sum('total_cogs');

        // This month's COGS
        $monthCogs = CogsHistory::where('store_id', $storeId)
            ->where('created_at', '>=', $thisMonth)
            ->sum('total_cogs');

        // Last month's COGS for comparison
        $lastMonthCogs = CogsHistory::where('store_id', $storeId)
            ->whereBetween('created_at', [$lastMonth, $thisMonth->copy()->subDay()])
            ->sum('total_cogs');

        // Calculate growth percentage
        $growthPercentage = $lastMonthCogs > 0
            ? (($monthCogs - $lastMonthCogs) / $lastMonthCogs) * 100
            : 0;

        // Products with recipes count
        $productsWithRecipes = Product::where('store_id', $storeId)
            ->whereHas('recipes')
            ->count();

        // Total products count
        $totalProducts = Product::where('store_id', $storeId)->count();

        // Recipe coverage percentage
        $recipeCoverage = $totalProducts > 0
            ? ($productsWithRecipes / $totalProducts) * 100
            : 0;

        return [
            Stat::make('Today\'s COGS', 'Rp ' . number_format($todayCogs, 0, ',', '.'))
                ->description('Cost of goods sold today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color($todayCogs > 0 ? 'success' : 'gray'),

            Stat::make('Monthly COGS', 'Rp ' . number_format($monthCogs, 0, ',', '.'))
                ->description(
                    $growthPercentage > 0 ?
                        '+' . number_format($growthPercentage, 1) . '% from last month' :
                        number_format($growthPercentage, 1) . '% from last month'
                )
                ->descriptionIcon($growthPercentage > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($growthPercentage > 0 ? 'success' : ($growthPercentage < 0 ? 'danger' : 'gray')),

            Stat::make('Recipe Coverage', number_format($recipeCoverage, 1) . '%')
                ->description("{$productsWithRecipes} of {$totalProducts} products have recipes")
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color($recipeCoverage > 50 ? 'success' : ($recipeCoverage > 25 ? 'warning' : 'danger')),
        ];
    }
}
