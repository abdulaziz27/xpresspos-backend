<?php

namespace App\Filament\Owner\Resources\ProductPriceHistories\Pages;

use App\Filament\Owner\Resources\ProductPriceHistories\ProductPriceHistoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ListProductPriceHistories extends ListRecords
{
    protected static string $resource = ProductPriceHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pricing_analysis')
                ->label('Pricing Analysis')
                ->icon('heroicon-o-chart-bar-square')
                ->color('info')
                ->action(function () {
                    // TODO: Implement pricing analysis functionality
                    Notification::make()
                        ->title('Pricing analysis feature will be implemented soon')
                        ->success()
                        ->send();
                }),

            Action::make('export')
                ->label('Export History')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    // TODO: Implement export functionality
                    Notification::make()
                        ->title('Export functionality will be implemented soon')
                        ->success()
                        ->send();
                }),
        ];
    }
}