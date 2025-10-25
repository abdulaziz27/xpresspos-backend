<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\Pages;

use App\Filament\Owner\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\StoreContext;
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
                ->url(fn (): string => route('landing.home'))
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
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());

        return [
            'all' => Tab::make('All Subscriptions')
                ->badge(Subscription::where('store_id', $storeId)->count()),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge(Subscription::where('store_id', $storeId)->where('status', 'active')->count())
                ->badgeColor('success'),
            
            'expiring_soon' => Tab::make('Expiring Soon')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('status', 'active')
                          ->where('ends_at', '<=', now()->addDays(30))
                          ->where('ends_at', '>', now())
                )
                ->badge(Subscription::where('store_id', $storeId)
                    ->where('status', 'active')
                    ->where('ends_at', '<=', now()->addDays(30))
                    ->where('ends_at', '>', now())
                    ->count())
                ->badgeColor('warning'),
            
            'suspended' => Tab::make('Suspended')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended'))
                ->badge(Subscription::where('store_id', $storeId)->where('status', 'suspended')->count())
                ->badgeColor('danger'),
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
        $storeContext = app(StoreContext::class);
        
        return Subscription::where('store_id', $storeContext->current(auth()->user()))
            ->where('status', 'active')
            ->exists();
    }
}