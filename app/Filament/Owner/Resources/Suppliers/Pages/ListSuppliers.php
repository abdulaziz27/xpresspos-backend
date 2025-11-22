<?php

namespace App\Filament\Owner\Resources\Suppliers\Pages;

use App\Filament\Owner\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah')
                ->visible(fn () => SupplierResource::canCreate()),
        ];
    }
}

