<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Stores', Store::count())
                ->description('Active stores in system')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('success'),

            Stat::make('Total Users', User::count())
                ->description('All system users')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Active Subscriptions', Subscription::where('status', 'active')->count())
                ->description('Currently active subscriptions')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('Monthly Revenue', 'Rp ' . number_format(Subscription::where('status', 'active')->sum('amount'), 0, ',', '.'))
                ->description('Total monthly subscription revenue')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
