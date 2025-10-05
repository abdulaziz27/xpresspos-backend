<?php

namespace App\Filament\Owner\Resources\InventoryMovements\Pages;

use App\Filament\Owner\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryMovement extends CreateRecord
{
    protected static string $resource = InventoryMovementResource::class;
}
