<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\Widgets;

use App\Models\Subscription;
use App\Services\StoreContext;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        $activeSubscription = Subscription::where('store_id', $storeId)
            ->where('status', 'active')
            ->first();

        $totalPayments = $activeSubscription 
            ? $activeSubscription->subscriptionPayments()->where('status', 'paid')->sum('amount')
            : 0;

        $nextPaymentDate = $activeSubscription?->ends_at;
        $daysUntilRenewal = $nextPaymentDate ? $nextPaymentDate->diffInDays() : null;

        return [
            Stat::make('Current Plan', $activeSubscription?->plan?->name ?? 'No Active Plan')
                ->description($activeSubscription ? 'Active subscription' : 'No active subscription')
                ->descriptionIcon($activeSubscription ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($activeSubscription ? 'success' : 'danger'),

            Stat::make('Next Renewal', $nextPaymentDate ? $nextPaymentDate->format('M j, Y') : 'N/A')
                ->description($daysUntilRenewal ? "{$daysUntilRenewal} days remaining" : 'No renewal scheduled')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($daysUntilRenewal && $daysUntilRenewal <= 7 ? 'warning' : 'primary'),

            Stat::make('Total Paid', 'Rp ' . number_format($totalPayments, 0, ',', '.'))
                ->description('Lifetime subscription payments')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}