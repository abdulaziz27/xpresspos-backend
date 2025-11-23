<?php

namespace App\Filament\Owner\Resources\TableOccupancyHistories\Pages;

use App\Filament\Owner\Resources\TableOccupancyHistories\TableOccupancyHistoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ListTableOccupancyHistories extends ListRecords
{
    protected static string $resource = TableOccupancyHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analytics')
                ->label('Table Analytics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->action(function () {
                    // TODO: Implement table analytics functionality
                    Notification::make()
                        ->title('Table analytics feature will be implemented soon')
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