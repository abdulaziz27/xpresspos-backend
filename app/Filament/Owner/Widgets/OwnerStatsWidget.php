<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Member;
use App\Services\GlobalFilterService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class OwnerStatsWidget extends BaseWidget
{
    /**
     * UPDATED: Now using GlobalFilterService for unified multi-store dashboard
     * 
     * Filter is controlled by GlobalFilterWidget (tenant, store, date range)
     * Widget automatically refreshes when filter changes
     */

    #[On('filter-updated')]
    public function refreshWidget(): void
    {
        // Trigger refresh when global filter changes
        $this->resetState();
    }

    protected function getStats(): array
    {
        $globalFilter = app(GlobalFilterService::class);
        
        // Get current filter values
        $tenantId = $globalFilter->getCurrentTenantId();
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant(); // Array of store IDs
        $dateRange = $globalFilter->getCurrentDateRange();
        $summary = $globalFilter->getFilterSummary();
        
        if (!$tenantId || empty($storeIds)) {
            return [
                Stat::make('No Data', '0')
                    ->description('No stores found for current tenant')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        // Build queries using store IDs (legacy support for models without tenant_id)
        $ordersQuery = Order::whereIn('store_id', $storeIds);
        $paymentsQuery = Payment::whereIn('store_id', $storeIds);
        $productsQuery = Product::whereIn('store_id', $storeIds);
        $membersQuery = Member::whereIn('store_id', $storeIds);

        // Apply date range for time-based metrics
        $ordersCount = (clone $ordersQuery)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        $revenue = (clone $paymentsQuery)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        // Total counts (not date-filtered)
        $totalProducts = $productsQuery->count();
        $totalMembers = $membersQuery->where('is_active', true)->count();

        // Build description with filter info
        $storeLabel = $summary['store'];
        $dateLabel = $summary['date_preset_label'];

        return [
            Stat::make('Total Transaksi', number_format($ordersCount, 0, ',', '.'))
                ->description("$storeLabel • $dateLabel")
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Total Pendapatan', 'Rp ' . number_format($revenue, 0, ',', '.'))
                ->description("$storeLabel • $dateLabel")
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart([15, 4, 10, 22, 13, 7, 10, 14]),

            Stat::make('Total Produk', number_format($totalProducts, 0, ',', '.'))
                ->description("$storeLabel • Seluruh waktu")
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Member Aktif', number_format($totalMembers, 0, ',', '.'))
                ->description("$storeLabel • Seluruh waktu")
                ->descriptionIcon('heroicon-o-user-group')
                ->color('warning'),
        ];
    }
}
