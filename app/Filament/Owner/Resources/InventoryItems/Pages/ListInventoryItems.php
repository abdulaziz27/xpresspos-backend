<?php

namespace App\Filament\Owner\Resources\InventoryItems\Pages;

use App\Filament\Owner\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;
}

