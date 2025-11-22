<?php

namespace App\Filament\Owner\Resources\InventoryTransfers\Pages;

use App\Filament\Owner\Resources\InventoryTransfers\InventoryTransferResource;
use Filament\Resources\Pages\EditRecord;

class EditInventoryTransfer extends EditRecord
{
    protected static string $resource = InventoryTransferResource::class;

    /**
     * No delete action - transfers are audit trail documents.
     */
    protected function getHeaderActions(): array
    {
        return [
            // No delete action - transfers cannot be deleted for audit trail
        ];
    }
}

