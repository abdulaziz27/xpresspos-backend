<?php

namespace App\Filament\Owner\Resources\InventoryAdjustments\Pages;

use App\Filament\Owner\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryAdjustments extends ListRecords
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah')
                ->visible(fn () => InventoryAdjustmentResource::canCreate()),
        ];
    }
}


