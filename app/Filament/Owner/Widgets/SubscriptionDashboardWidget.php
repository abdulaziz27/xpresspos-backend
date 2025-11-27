<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Widgets\Concerns\ResolvesOwnerDashboardFilters;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\DashboardFilterService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionDashboardWidget extends Widget
{
    use InteractsWithPageFilters;
    use ResolvesOwnerDashboardFilters;

    protected string $view = 'filament.owner.widgets.subscription-dashboard';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $filters = $this->dashboardFilters();
        $tenantId = $filters['tenant_id'];

        if (! $tenantId) {
            return $this->emptyViewData();
        }

        $tenant = Tenant::find($tenantId);
        
        if (! $tenant) {
            return $this->emptyViewData();
        }

        $activeSubscription = $tenant->activeSubscription();
        
        if ($activeSubscription) {
            $activeSubscription->load('plan');
        }

        $recentPayments = $this->buildTenantPaymentsQuery($tenant->id)
        ->latest()
        ->limit(5)
        ->get();

        $upcomingRenewal = ($activeSubscription && (int) $activeSubscription->ends_at->diffInDays() <= 30)
            ? $activeSubscription 
            : null;

        $pendingPayments = $this->buildTenantPaymentsQuery($tenant->id)
            ->where('status', 'pending')
        ->get();

        return [
            'activeSubscription' => $activeSubscription,
            'recentPayments' => $recentPayments,
            'upcomingRenewal' => $upcomingRenewal,
            'pendingPayments' => $pendingPayments,
            'filterContext' => $this->dashboardFilterContextLabel(),
        ];
    }

    protected function buildTenantPaymentsQuery(string $tenantId)
    {
        return SubscriptionPayment::withoutGlobalScopes()
            ->where(function (Builder $query) use ($tenantId) {
                $query->whereHas('subscription', fn (Builder $sub) => $sub->where('tenant_id', $tenantId))
                    ->orWhereHas('landingSubscription', fn (Builder $sub) => $sub->where('tenant_id', $tenantId));
            })
            ->with(['subscription.plan', 'landingSubscription']);
    }

    protected function emptyViewData(): array
    {
        return [
            'activeSubscription' => null,
            'recentPayments' => collect(),
            'upcomingRenewal' => null,
            'pendingPayments' => collect(),
            'filterContext' => $this->dashboardFilterContextLabel(),
        ];
    }

    public static function canView(): bool
    {
        return (bool) app(DashboardFilterService::class)->getCurrentTenantId();
    }
}