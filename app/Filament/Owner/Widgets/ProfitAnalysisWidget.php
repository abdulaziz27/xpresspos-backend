<?php

namespace App\Filament\Owner\Widgets;

use App\Services\FnBAnalyticsService;
use App\Services\GlobalFilterService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class ProfitAnalysisWidget extends BaseWidget
{
    /**
     * UPDATED: Now using GlobalFilterService for unified multi-store dashboard
     * 
     * Widget automatically refreshes when global filter changes
     * Shows combined profit data for all selected stores
     */

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        // Trigger refresh when global filter changes
        $this->resetState();
    }

    protected function getStats(): array
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Get current filter values from global filter
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();
        $dateRange = $globalFilter->getCurrentDateRange();
        $summary = $globalFilter->getFilterSummary();
        
        if (empty($storeIds)) {
            return [];
        }

        try {
            $analyticsService = app(FnBAnalyticsService::class);
            
            // Use date preset from global filter
            $range = $summary['date_preset'] ?? 'this_month';
            
            // Get profit analysis for all selected stores
            $profitAnalysis = $analyticsService->getProfitAnalysisForStores($storeIds, $range);

            if (empty($profitAnalysis)) {
                // Don't show widget if no sales
                return [];
            }

            $topProduct = $profitAnalysis[0] ?? null;
            $totalProfit = collect($profitAnalysis)->sum('profit');
            $avgMargin = collect($profitAnalysis)->avg('margin_percent') ?? 0;

            return [
                Stat::make('Produk Teratas', $topProduct['product_name'] ?? 'N/A')
                    ->description($topProduct ? 'Profit: Rp ' . number_format($topProduct['profit'], 0, ',', '.') : 'Tidak ada data')
                    ->color('success'),

                Stat::make('Total Profit', 'Rp ' . number_format($totalProfit, 0, ',', '.'))
                    ->description($summary['store'] . ' • ' . $summary['date_preset_label'])
                    ->color($totalProfit > 0 ? 'success' : 'gray'),

                Stat::make('Rata-rata Margin', number_format($avgMargin, 1) . '%')
                    ->description('Di seluruh produk (' . count($profitAnalysis) . ' produk)')
                    ->color($avgMargin >= 30 ? 'success' : ($avgMargin >= 20 ? 'warning' : 'danger')),
            ];

        } catch (\Exception $e) {
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

        // Show only if there are sales for current filter
        $dateRange = $globalFilter->getCurrentDateRange();

        return \App\Models\CogsHistory::whereIn('store_id', $storeIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->where('quantity_sold', '>', 0)
            ->exists();
    }
}