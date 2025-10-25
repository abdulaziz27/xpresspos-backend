<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\StoreContext;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionDashboardWidget extends Widget
{
    protected string $view = 'filament.owner.widgets.subscription-dashboard';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        $activeSubscription = Subscription::where('store_id', $storeId)
            ->where('status', 'active')
            ->with('plan')
            ->first();

        $recentPayments = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeId) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeId) {
                $subQuery->where('id', $storeId);
            });
        })->with(['subscription.plan', 'landingSubscription'])
        ->latest()
        ->limit(5)
        ->get();

        $upcomingRenewal = $activeSubscription && $activeSubscription->ends_at->diffInDays() <= 30 
            ? $activeSubscription 
            : null;

        $pendingPayments = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeId) {
            $query->where('store_id', $storeId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeId) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeId) {
                $subQuery->where('id', $storeId);
            });
        })->where('status', 'pending')
        ->with(['subscription.plan', 'landingSubscription'])
        ->get();

        return [
            'activeSubscription' => $activeSubscription,
            'recentPayments' => $recentPayments,
            'upcomingRenewal' => $upcomingRenewal,
            'pendingPayments' => $pendingPayments,
        ];
    }

    public static function canView(): bool
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        // Show widget only if store has subscription-related data
        return Subscription::where('store_id', $storeId)->exists() ||
               SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeId) {
                   $query->where('store_id', $storeId);
               })->exists();
    }
}