<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Services\FnBAnalyticsService;
use App\Services\GlobalFilterService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProfitAnalysisWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    protected function getDescription(): ?string
    {
        return $this->dashboardFilterContextLabel();
    }

    public function updatedPageFilters(): void
    {
        $this->cachedStats = null;
    }

    protected function getStats(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $summary = $this->dashboardFilterSummary();
        
        if (empty($storeIds)) {
            return [];
        }

        try {
            $analyticsService = app(FnBAnalyticsService::class);
            
            $preset = $filters['date_preset'] ?? 'this_month';
            $customRange = $preset === 'custom'
                ? [$filters['range']['start'], $filters['range']['end']]
                : null;

            $profitAnalysis = $analyticsService->getProfitAnalysisForStores($storeIds, $preset, $customRange);

            if (empty($profitAnalysis)) {
                return [];
            }

            $topProduct = $profitAnalysis[0] ?? null;
            $totalProfit = collect($profitAnalysis)->sum('profit');
            $avgMargin = collect($profitAnalysis)->avg('margin_percent') ?? 0;

            $storeLabel = $summary['store'] ?? 'Semua Cabang';
            $tenantLabel = $summary['tenant'] ?? 'Tenant';

            return [
                Stat::make('Produk Teratas', $topProduct['product_name'] ?? 'N/A')
                    ->description($topProduct ? 'Profit: Rp ' . number_format($topProduct['profit'], 0, ',', '.') : 'Tidak ada data')
                    ->color('success'),

                Stat::make('Total Profit', 'Rp ' . number_format($totalProfit, 0, ',', '.'))
                    ->description("{$tenantLabel} • {$storeLabel}")
                    ->color($totalProfit > 0 ? 'success' : 'gray'),

                Stat::make('Rata-rata Margin', number_format($avgMargin, 1) . '%')
                    ->description('Dari ' . count($profitAnalysis) . ' produk')
                    ->color($avgMargin >= 30 ? 'success' : ($avgMargin >= 20 ? 'warning' : 'danger')),
            ];
        } catch (\Exception $e) {
            report($e);

            return [
                Stat::make('Error', 'Tidak dapat memuat data')
                    ->description('Silakan coba lagi nanti')
                    ->color('danger'),
            ];
        }
    }

    public static function canView(): bool
    {
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
        
        if (empty($storeIds)) {
            return false;
        }

        $dateRange = $globalFilter->getCurrentDateRange();

        return \App\Models\CogsHistory::withoutGlobalScopes()
            ->whereIn('store_id', $storeIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('quantity_sold', '>', 0)
            ->exists();
    }
}