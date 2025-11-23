<?php

namespace App\Filament\Owner\Resources\InventoryTransfers\Pages;

use App\Filament\Owner\Resources\InventoryTransfers\InventoryTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryTransfers extends ListRecords
{
    protected static string $resource = InventoryTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah')
                ->visible(fn () => InventoryTransferResource::canCreate()),
        ];
    }
}

