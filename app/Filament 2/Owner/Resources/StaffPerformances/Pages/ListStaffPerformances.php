<?php

namespace App\Filament\Owner\Resources\StaffPerformances\Pages;

use App\Filament\Owner\Resources\StaffPerformances\StaffPerformanceResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ListStaffPerformances extends ListRecords
{
    protected static string $resource = StaffPerformanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_report')
                ->label('Generate Report')
                ->icon('heroicon-o-document-chart-bar')
                ->color('info')
                ->action(function () {
                    // TODO: Implement performance report generation
                    Notification::make()
                        ->title('Performance report generation will be implemented soon')
                        ->success()
                        ->send();
                }),

            Action::make('export')
                ->label('Export Performance')
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