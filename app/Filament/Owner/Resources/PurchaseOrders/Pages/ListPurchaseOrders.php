<?php

namespace App\Filament\Owner\Resources\PurchaseOrders\Pages;

use App\Filament\Owner\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah')
                ->visible(fn () => PurchaseOrderResource::canCreate()),
        ];
    }
}

