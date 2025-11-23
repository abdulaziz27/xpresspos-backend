<?php

namespace App\Filament\Owner\Resources\SubscriptionUsageResource\Pages;

use App\Filament\Owner\Resources\SubscriptionUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscriptionUsage extends ViewRecord
{
    protected static string $resource = SubscriptionUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_subscription')
                ->label('Lihat Langganan')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url(fn (): string => 
                    $this->record->subscription 
                        ? route('filament.owner.resources.subscriptions.view', $this->record->subscription)
                        : '#'
                )
                ->visible(fn (): bool => $this->record->subscription !== null),
        ];
    }
}

