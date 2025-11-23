<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\CogsHistory;
use App\Models\Product;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CogsSummaryWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $tenantId = $filters['tenant_id'] ?? null;

        if (! $tenantId || empty($storeIds)) {
            return [
                Stat::make('COGS Hari Ini', 'Rp 0')
                    ->description('Tenant atau cabang belum dipilih')
                    ->color('warning'),
                Stat::make('COGS Bulan Ini', 'Rp 0')
                    ->description('Tenant atau cabang belum dipilih')
                    ->color('warning'),
                Stat::make('Cakupan Resep', '0%')
                    ->description('Tenant atau cabang belum dipilih')
                    ->color('warning'),
            ];
        }

        $todayRange = [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()];
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = $thisMonthStart->copy()->subDay()->endOfDay();

        $todayCogs = CogsHistory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->whereBetween('created_at', $todayRange)
            ->sum('total_cogs');

        $monthCogs = CogsHistory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->where('created_at', '>=', $thisMonthStart)
            ->sum('total_cogs');

        $lastMonthCogs = CogsHistory::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('store_id', $storeIds)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_cogs');

        $growthPercentage = $lastMonthCogs > 0
            ? (($monthCogs - $lastMonthCogs) / $lastMonthCogs) * 100
            : 0;

        $productsQuery = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', true);

        $productsWithRecipes = (clone $productsQuery)
            ->whereHas('recipes')
            ->count();

        $totalProducts = (clone $productsQuery)->count();

        $recipeCoverage = $totalProducts > 0
            ? ($productsWithRecipes / $totalProducts) * 100
            : 0;

        return [
            Stat::make('COGS Hari Ini', 'Rp ' . number_format($todayCogs, 0, ',', '.'))
                ->description('Biaya pokok penjualan hari ini')
                ->color($todayCogs > 0 ? 'success' : 'gray'),

            Stat::make('COGS Bulan Ini', 'Rp ' . number_format($monthCogs, 0, ',', '.'))
                ->description(
                    $growthPercentage > 0
                        ? '+' . number_format($growthPercentage, 1) . '% dibanding bulan lalu'
                        : number_format($growthPercentage, 1) . '% dibanding bulan lalu'
                )
                ->color($growthPercentage > 0 ? 'success' : ($growthPercentage < 0 ? 'danger' : 'gray')),

            Stat::make('Cakupan Resep', number_format($recipeCoverage, 1) . '%')
                ->description("{$productsWithRecipes} dari {$totalProducts} produk memiliki resep")
                ->color($recipeCoverage > 50 ? 'success' : ($recipeCoverage > 25 ? 'warning' : 'danger')),
        ];
    }
}
