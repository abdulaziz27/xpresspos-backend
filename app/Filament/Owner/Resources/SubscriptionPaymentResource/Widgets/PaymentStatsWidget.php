<?php

namespace App\Filament\Owner\Resources\SubscriptionPaymentResource\Widgets;

use App\Models\SubscriptionPayment;
use App\Services\StoreContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PaymentStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        $baseQuery = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeId) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeId) {
                $subQuery->where('id', $storeId);
            });
        });

        $totalPaid = $baseQuery->clone()->where('status', 'paid')->sum('amount');
        $thisMonthPaid = $baseQuery->clone()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');
        
        $pendingCount = $baseQuery->clone()->where('status', 'pending')->count();
        $failedCount = $baseQuery->clone()->where('status', 'failed')->count();

        $lastMonthPaid = $baseQuery->clone()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonth()->startOfMonth())
            ->where('paid_at', '<=', now()->subMonth()->endOfMonth())
            ->sum('amount');

        $monthlyGrowth = $lastMonthPaid > 0 
            ? (($thisMonthPaid - $lastMonthPaid) / $lastMonthPaid) * 100 
            : 0;

        return [
            Stat::make('Total Paid', 'Rp ' . number_format($totalPaid, 0, ',', '.'))
                ->description('All-time subscription payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('This Month', 'Rp ' . number_format($thisMonthPaid, 0, ',', '.'))
                ->description($monthlyGrowth >= 0 
                    ? '+' . number_format($monthlyGrowth, 1) . '% from last month'
                    : number_format($monthlyGrowth, 1) . '% from last month'
                )
                ->descriptionIcon($monthlyGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthlyGrowth >= 0 ? 'success' : 'danger'),

            Stat::make('Pending Payments', $pendingCount)
                ->description($failedCount . ' failed payments')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'success'),
        ];
    }
}