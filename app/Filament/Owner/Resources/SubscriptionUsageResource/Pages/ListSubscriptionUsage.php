<?php

namespace App\Filament\Owner\Resources\SubscriptionUsageResource\Pages;

use App\Filament\Owner\Resources\SubscriptionUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionUsage extends ListRecords
{
    protected static string $resource = SubscriptionUsageResource::class;

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

