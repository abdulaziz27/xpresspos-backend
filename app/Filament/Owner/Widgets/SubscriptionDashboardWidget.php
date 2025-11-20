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
        $store = \App\Models\Store::find($storeId);
        
        if (!$store || !$store->tenant_id) {
            return [
                'activeSubscription' => null,
                'recentPayments' => collect(),
                'upcomingRenewal' => null,
                'pendingPayments' => collect(),
            ];
        }

        $tenant = $store->tenant;
        $activeSubscription = $tenant->activeSubscription();
        
        if ($activeSubscription) {
            $activeSubscription->load('plan');
        }

        $tenantId = $tenant->id;
        $recentPayments = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->with(['subscription.plan', 'landingSubscription'])
        ->latest()
        ->limit(5)
        ->get();

        $upcomingRenewal = $activeSubscription && (int) $activeSubscription->ends_at->diffInDays() <= 30 
            ? $activeSubscription 
            : null;

        $pendingPayments = SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
        })->orWhereHas('landingSubscription', function (Builder $query) use ($tenantId) {
            $query->where('tenant_id', $tenantId);
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
        $store = \App\Models\Store::find($storeId);
        
        if (!$store || !$store->tenant_id) {
            return false;
        }

        $tenant = $store->tenant;
        
        // Show widget only if tenant has subscription-related data
        return $tenant->activeSubscription() !== null ||
               SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($tenant) {
                   $query->where('tenant_id', $tenant->id);
               })->orWhereHas('landingSubscription', function (Builder $query) use ($tenant) {
                   $query->where('tenant_id', $tenant->id);
               })->exists();
    }
}