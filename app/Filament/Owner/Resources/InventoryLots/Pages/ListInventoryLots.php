<?php

namespace App\Filament\Owner\Resources\InventoryLots\Pages;

use App\Filament\Owner\Resources\InventoryLots\InventoryLotResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLots extends ListRecords
{
    protected static string $resource = InventoryLotResource::class;
}

