<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OwnerStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected ?string $heading = 'Ringkasan Performa';

    public function updatedPageFilters(): void
    {
        $this->cachedStats = null;
    }

    protected function getDescription(): ?string
    {
        return $this->dashboardFilterContextLabel();
    }

    protected function getStats(): array
    {
        $filters = $this->dashboardFilters();
        $storeIds = $this->dashboardStoreIds();
        $summary = $this->dashboardFilterSummary();
        $tenantId = $filters['tenant_id'];
        
        if (! $tenantId || empty($storeIds)) {
            return [
                Stat::make('Data Tidak Tersedia', '0')
                    ->description('Tenant atau cabang belum dipilih.')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        $dateRange = $filters['range'];

        $ordersQuery = Order::whereIn('store_id', $storeIds);
        $paymentsQuery = Payment::whereIn('store_id', $storeIds);
        $productsQuery = Product::where('tenant_id', $tenantId)->where('status', true);
        $membersQuery = Member::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($filters, $storeIds) {
        if (! empty($filters['store_id'])) {
                    $query->where('store_id', $filters['store_id']);
                } else {
                    $query->whereIn('store_id', $storeIds)
                        ->orWhereNull('store_id');
        }
            });

        $ordersCount = (clone $ordersQuery)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        $revenue = (clone $paymentsQuery)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->sum('amount');

        $totalProducts = $productsQuery->count();
        $totalMembers = $membersQuery->where('is_active', true)->count();

        $tenantLabel = $summary['tenant'] ?? 'Tenant';
        $storeLabel = $summary['store'] ?? 'Semua Cabang';
        $dateLabel = $summary['date_preset_label'] ?? 'Periode berjalan';

        $context = "{$tenantLabel} • {$storeLabel} • {$dateLabel}";
        $allTimeContext = "{$tenantLabel} • {$storeLabel} • Seluruh waktu";

        return [
            Stat::make('Total Transaksi', number_format($ordersCount, 0, ',', '.'))
                ->description($context)
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Total Pendapatan', 'Rp ' . number_format($revenue, 0, ',', '.'))
                ->description($context)
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success')
                ->chart([15, 4, 10, 22, 13, 7, 10, 14]),

            Stat::make('Total Produk', number_format($totalProducts, 0, ',', '.'))
                ->description($allTimeContext)
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Member Aktif', number_format($totalMembers, 0, ',', '.'))
                ->description($allTimeContext)
                ->descriptionIcon('heroicon-o-user-group')
                ->color('warning'),
        ];
    }
}
