<?php

namespace App\Filament\Owner\Resources\Products\Pages;

use App\Filament\Owner\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculateCost')
                ->label('Hitung HPP dari Resep')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Hitung HPP dari Resep')
                ->modalDescription('HPP akan dihitung dari resep aktif produk ini. Lanjutkan?')
                ->action(function () {
                    $this->record->recalculateCostPriceFromRecipe();
                    $this->refreshFormData(['cost_price']);
                    
                    Notification::make()
                        ->title('HPP berhasil dihitung dari resep')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()->label('Hapus'),
        ];
    }
}
