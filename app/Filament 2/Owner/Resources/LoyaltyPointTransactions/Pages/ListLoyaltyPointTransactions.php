<?php

namespace App\Filament\Owner\Resources\LoyaltyPointTransactions\Pages;

use App\Filament\Owner\Resources\LoyaltyPointTransactions\LoyaltyPointTransactionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ListLoyaltyPointTransactions extends ListRecords
{
    protected static string $resource = LoyaltyPointTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Transactions')
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