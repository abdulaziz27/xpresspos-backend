<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Services\FnBAnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdvancedAnalyticsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected static ?int $sort = 2;
    
    public static function canView(): bool
    {
        return auth()->user()->hasFeature('advanced_analytics');
    }

    protected function getStats(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $summary = $this->dashboardFilterSummary();

        if (empty($storeIds) || ! ($filters['tenant_id'] ?? null)) {
            return [];
        }

        $preset = $filters['date_preset'] ?? 'today';
        $customRange = $preset === 'custom'
            ? [$filters['range']['start'], $filters['range']['end']]
            : null;

        $analyticsService = app(FnBAnalyticsService::class);
        $salesAnalytics = $analyticsService->getSalesAnalyticsForStores($storeIds, $preset, $customRange);
        $profitAnalysis = $analyticsService->getProfitAnalysisForStores($storeIds, $preset, $customRange);

        $totalProfit = collect($profitAnalysis)->sum('profit');
        $avgMargin = collect($profitAnalysis)->avg('margin_percent') ?? 0;
        $topProduct = collect($profitAnalysis)->first();
        $itemsSold = $salesAnalytics['summary']['total_items_sold'] ?? 0;

        $context = $summary['tenant'] ?? 'Tenant';
        $storeLabel = $summary['store'] ?? 'Semua Cabang';
        $dateLabel = $summary['date_preset_label'] ?? 'Periode Berjalan';
        $description = "{$context} • {$storeLabel} • {$dateLabel}";

        return [
            Stat::make('Total Profit', 'Rp ' . number_format($totalProfit, 0, ',', '.'))
                ->description($description)
                ->color($totalProfit > 0 ? 'success' : 'gray'),

            Stat::make('Rata-rata Margin', number_format($avgMargin, 1) . '%')
                ->description('Rata-rata margin produk pada periode ini')
                ->color($avgMargin >= 30 ? 'success' : ($avgMargin >= 20 ? 'warning' : 'danger')),

            Stat::make('Produk Teratas', $topProduct['product_name'] ?? 'Belum ada penjualan')
                ->description(
                    $topProduct
                        ? 'Terjual: ' . number_format($topProduct['quantity_sold'] ?? 0)
                        : 'Tidak ada transaksi pada periode ini'
                )
                ->color('info'),

            Stat::make('Unit Terjual', number_format($itemsSold, 0, ',', '.'))
                ->description('Total produk terjual pada periode yang dipilih')
                ->color($itemsSold > 0 ? 'warning' : 'gray'),
        ];
    }
}