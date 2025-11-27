<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\Pages;

use App\Filament\Owner\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\GlobalFilterService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upgrade_plan')
                ->label('Upgrade Plan')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->url(fn (): string => route('landing.pricing'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->hasActiveSubscription()),
            
            Actions\Action::make('billing_history')
                ->label('View All Payments')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (): string => 
                    class_exists('App\Filament\Owner\Resources\SubscriptionPaymentResource') 
                        ? \App\Filament\Owner\Resources\SubscriptionPaymentResource::getUrl('index')
                        : '#'
                )
                ->visible(fn (): bool => class_exists('App\Filament\Owner\Resources\SubscriptionPaymentResource')),
        ];
    }

    public function getTabs(): array
    {
        // Get current tenant ID using GlobalFilterService or fallback to user's current tenant
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();
        
        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }
        
        if (!$tenantId) {
            return [];
        }

        return [
            'all' => Tab::make('All Subscriptions')
                ->badge(Subscription::where('tenant_id', $tenantId)->count()),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(Subscription::where('tenant_id', $tenantId)->where('status', 'active')->count())
                ->badgeColor('success'),
            
            'expiring_soon' => Tab::make('Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('status', 'active')
                          ->where('ends_at', '<=', now()->addDays(30))
                          ->where('ends_at', '>', now())
                )
                ->badge(Subscription::where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->where('ends_at', '<=', now()->addDays(30))
                    ->where('ends_at', '>', now())
                    ->count())
                ->badgeColor('warning'),
            
            'inactive' => Tab::make('Tidak Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'inactive'))
                ->badge(Subscription::where('tenant_id', $tenantId)->where('status', 'inactive')->count())
                ->badgeColor('warning'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SubscriptionResource\Widgets\SubscriptionOverviewWidget::class,
        ];
    }

    private function hasActiveSubscription(): bool
    {
        // Get current tenant ID using GlobalFilterService or fallback to user's current tenant
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();
        
        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenant = auth()->user()?->currentTenant();
            if (!$tenant) {
                return false;
            }
            return $tenant->activeSubscription() !== null;
        }
        
        $tenant = \App\Models\Tenant::find($tenantId);
        return $tenant && $tenant->activeSubscription() !== null;
    }
}