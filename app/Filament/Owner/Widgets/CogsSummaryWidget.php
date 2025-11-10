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
                Stat::make('COGS Hari Ini', 'Rp 0')
                    ->description('Biaya pokok penjualan hari ini')
                    ->color('gray'),
                Stat::make('COGS Bulan Ini', 'Rp 0')
                    ->description('0.0% dibanding bulan lalu')
                    ->color('gray'),
                Stat::make('Cakupan Resep', '0.0%')
                    ->description('0 dari 0 produk memiliki resep')
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
            Stat::make('COGS Hari Ini', 'Rp ' . number_format($todayCogs, 0, ',', '.'))
                ->description('Biaya pokok penjualan hari ini')
                ->color($todayCogs > 0 ? 'success' : 'gray'),

            Stat::make('COGS Bulan Ini', 'Rp ' . number_format($monthCogs, 0, ',', '.'))
                ->description(
                    $growthPercentage > 0 ?
                        '+' . number_format($growthPercentage, 1) . '% dibanding bulan lalu' :
                        number_format($growthPercentage, 1) . '% dibanding bulan lalu'
                )
                ->color($growthPercentage > 0 ? 'success' : ($growthPercentage < 0 ? 'danger' : 'gray')),

            Stat::make('Cakupan Resep', number_format($recipeCoverage, 1) . '%')
                ->description("{$productsWithRecipes} dari {$totalProducts} produk memiliki resep")
                ->color($recipeCoverage > 50 ? 'success' : ($recipeCoverage > 25 ? 'warning' : 'danger')),
        ];
    }
}
