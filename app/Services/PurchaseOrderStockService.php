<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\StockLevel;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderStockService
{
    /**
     * Process purchase order when status changes to 'received'.
     * Creates inventory lots, movements, and updates stock levels using FIFO.
     */
    public function processReceivedPurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        // Only process if status is 'received'
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_RECEIVED) {
            return;
        }

        DB::transaction(function () use ($purchaseOrder) {
            foreach ($purchaseOrder->items as $item) {
                // Only process items with quantity_received > 0
                if ($item->quantity_received <= 0) {
                    continue;
                }

                // Check if this item already has movements (idempotency per item)
                $existingMovement = InventoryMovement::where('reference_type', PurchaseOrderItem::class)
                    ->where('reference_id', $item->id)
                    ->first();

                if ($existingMovement) {
                    // Item already processed, skip to avoid duplicates
                    // Note: If quantity_received changed, user should manually adjust or create new movement
                    continue;
                }

                $this->processPurchaseOrderItem($purchaseOrder, $item);
            }

            // Update received_at timestamp
            if (!$purchaseOrder->received_at) {
                $purchaseOrder->received_at = now();
                $purchaseOrder->saveQuietly();
            }
        });
    }

    /**
     * Process a single purchase order item.
     */
    protected function processPurchaseOrderItem(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): void
    {
        $inventoryItem = $item->inventoryItem;
        if (!$inventoryItem) {
            return;
        }

        $quantityReceived = (float) $item->quantity_received;
        $unitCost = (float) $item->unit_cost;

        // Create inventory lot for FIFO tracking
        $lot = $this->createInventoryLot($purchaseOrder, $item, $inventoryItem, $quantityReceived, $unitCost);

        // Create inventory movement
        $movement = $this->createInventoryMovement($purchaseOrder, $item, $inventoryItem, $quantityReceived, $unitCost, $lot);

        // Update stock level
        $this->updateStockLevel($inventoryItem, $movement);
    }

    /**
     * Create inventory lot for FIFO tracking.
     */
    protected function createInventoryLot(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        InventoryItem $inventoryItem,
        float $quantity,
        float $unitCost
    ): InventoryLot {
        // Generate lot code: PO-{PO_NUMBER}-{ITEM_ID}-{TIMESTAMP}
        $lotCode = sprintf(
            'PO-%s-%s-%s',
            $purchaseOrder->po_number,
            Str::substr($item->id, 0, 8),
            now()->format('YmdHis')
        );

        return InventoryLot::create([
            'tenant_id' => $purchaseOrder->tenant_id,
            'store_id' => $purchaseOrder->store_id,
            'inventory_item_id' => $inventoryItem->id,
            'lot_code' => $lotCode,
            'mfg_date' => null, // Can be set from PO if available
            'exp_date' => null, // Can be set from PO if available
            'initial_qty' => $quantity,
            'remaining_qty' => $quantity,
            'unit_cost' => $unitCost,
            'status' => 'active',
        ]);
    }

    /**
     * Create inventory movement record.
     */
    protected function createInventoryMovement(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        InventoryItem $inventoryItem,
        float $quantity,
        float $unitCost,
        ?InventoryLot $lot = null
    ): InventoryMovement {
        return InventoryMovement::create([
            'tenant_id' => $purchaseOrder->tenant_id,
            'store_id' => $purchaseOrder->store_id,
            'inventory_item_id' => $inventoryItem->id,
            'user_id' => auth()->id(),
            'type' => InventoryMovement::TYPE_PURCHASE,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'reason' => "Purchase Order: {$purchaseOrder->po_number}",
            'reference_type' => PurchaseOrderItem::class, // Reference to item, not PO
            'reference_id' => $item->id,
            'notes' => "Item: {$inventoryItem->name}, Lot: " . ($lot?->lot_code ?? 'N/A') . ", PO: {$purchaseOrder->po_number}",
        ]);
    }

    /**
     * Update stock level after movement.
     */
    protected function updateStockLevel(InventoryItem $inventoryItem, InventoryMovement $movement): void
    {
        $stockLevel = StockLevel::getOrCreateForInventoryItem(
            $inventoryItem->id,
            $movement->store_id
        );

        $stockLevel->updateFromMovement($movement);
    }

    /**
     * Get FIFO lots for an inventory item (oldest first).
     */
    public static function getFifoLots(string $inventoryItemId, string $storeId, float $quantity): array
    {
        return InventoryLot::where('inventory_item_id', $inventoryItemId)
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at', 'asc') // FIFO: oldest first
            ->orderBy('id', 'asc') // Secondary sort for consistency
            ->get()
            ->toArray();
    }

    /**
     * Consume stock from FIFO lots.
     * Returns array of lots consumed with quantities.
     */
    public static function consumeFromFifoLots(string $inventoryItemId, string $storeId, float $quantity): array
    {
        $lots = self::getFifoLots($inventoryItemId, $storeId, $quantity);
        $remainingQuantity = $quantity;
        $consumed = [];

        foreach ($lots as $lotData) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $lot = InventoryLot::find($lotData['id']);
            if (!$lot || $lot->remaining_qty <= 0) {
                continue;
            }

            $consumeQty = min($remainingQuantity, (float) $lot->remaining_qty);
            $lot->remaining_qty -= $consumeQty;
            $remainingQuantity -= $consumeQty;

            // Mark as depleted if no remaining quantity
            if ($lot->remaining_qty <= 0) {
                $lot->status = 'depleted';
            }

            $lot->save();

            $consumed[] = [
                'lot_id' => $lot->id,
                'lot_code' => $lot->lot_code,
                'quantity' => $consumeQty,
                'unit_cost' => (float) $lot->unit_cost,
                'total_cost' => $consumeQty * (float) $lot->unit_cost,
            ];
        }

        if ($remainingQuantity > 0) {
            throw new \Exception("Insufficient stock in FIFO lots. Need {$quantity}, but only " . ($quantity - $remainingQuantity) . " available.");
        }

        return $consumed;
    }
}

