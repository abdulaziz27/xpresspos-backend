<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;

class PurchaseOrderObserver
{
    /**
     * Handle the PurchaseOrder "updated" event.
     */
    public function updated(PurchaseOrder $purchaseOrder): void
    {
        // Handle status changes to 'received'
        if ($purchaseOrder->wasChanged('status') && $purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            $this->handleReceivedStatus($purchaseOrder);
        }
    }

    /**
     * Handle when purchase order status changes to 'received'.
     */
    private function handleReceivedStatus(PurchaseOrder $purchaseOrder): void
    {
        try {
            Log::info('Purchase order received, generating inventory lots and movements', [
                'purchase_order_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'store_id' => $purchaseOrder->store_id,
            ]);

            // Generate inventory lots and movements
            $purchaseOrder->generateInventoryLotsAndMovements();

            Log::info('Successfully generated inventory lots and movements for purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate inventory lots and movements for purchase order', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to prevent status change if critical
            throw $e;
        }
    }
}

