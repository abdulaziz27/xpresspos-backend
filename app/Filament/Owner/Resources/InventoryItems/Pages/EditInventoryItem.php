<?php

namespace App\Filament\Owner\Resources\InventoryItems\Pages;

use App\Filament\Owner\Resources\InventoryItems\InventoryItemResource;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;
}

