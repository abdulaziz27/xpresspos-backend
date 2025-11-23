<?php

namespace App\Filament\Owner\Resources\PaymentMethodResource\Pages;

use App\Filament\Owner\Resources\PaymentMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentMethods extends ListRecords
{
    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_subscription')
                ->label('Lihat Langganan')
                ->icon('heroicon-o-credit-card')
                ->color('success')
                ->url(fn (): string => 
                    class_exists('App\Filament\Owner\Resources\SubscriptionResource') 
                        ? \App\Filament\Owner\Resources\SubscriptionResource::getUrl('index')
                        : '#'
                ),
        ];
    }
}

