<?php

namespace App\Filament\Owner\Resources\InventoryItems\Pages;

use App\Filament\Owner\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;
}

