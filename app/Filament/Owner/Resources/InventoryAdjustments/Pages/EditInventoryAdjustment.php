<?php

namespace App\Filament\Owner\Resources\InventoryAdjustments\Pages;

use App\Filament\Owner\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use Filament\Resources\Pages\EditRecord;

class EditInventoryAdjustment extends EditRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    /**
     * No delete action - adjustments are audit trail documents.
     */
    protected function getHeaderActions(): array
    {
        return [
            // No delete action - adjustments cannot be deleted for audit trail
        ];
    }
}


