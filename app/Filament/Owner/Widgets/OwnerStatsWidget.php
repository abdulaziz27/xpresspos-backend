<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OwnerStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $storeId = auth()->user()?->store_id;

        if (!$storeId) {
            return [];
        }

        return [
            Stat::make('Today\'s Orders', Order::where('store_id', $storeId)->whereDate('created_at', today())->count())
                ->description('Orders placed today')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('Today\'s Revenue', 'Rp ' . number_format(Payment::where('store_id', $storeId)->where('status', 'completed')->whereDate('created_at', today())->sum('amount'), 0, ',', '.'))
                ->description('Revenue generated today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Products', Product::where('store_id', $storeId)->count())
                ->description('Products in catalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('Active Members', Member::where('store_id', $storeId)->where('is_active', true)->count())
                ->description('Registered members')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
