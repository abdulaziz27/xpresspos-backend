<?php

namespace App\Filament\Owner\Resources\PurchaseOrders\Pages;

use App\Filament\Owner\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    /**
     * No delete action - purchase orders are audit trail documents.
     */
    protected function getHeaderActions(): array
    {
        return [
            // No delete action - purchase orders cannot be deleted for audit trail
        ];
    }
}

