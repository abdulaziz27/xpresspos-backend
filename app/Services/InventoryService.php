<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Models\CogsHistory;
use App\Jobs\SendLowStockNotification;
use App\Services\CogsService;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Adjust stock manually.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Stock adjustments are per inventory_item per store.
     * 
     * @param string $inventoryItemId The inventory item ID
     * @param float $quantity Quantity to adjust (positive for adjustment_in, negative for adjustment_out)
     * @param string $reason Reason for adjustment
     * @param float|null $unitCost Optional unit cost
     * @param string|null $notes Optional notes
     * @return array
     */
    public function adjustStock(
        string $inventoryItemId,
        float $quantity,
        string $reason,
        ?float $unitCost = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($inventoryItemId, $quantity, $reason, $unitCost, $notes) {
            $inventoryItem = InventoryItem::where('track_stock', true)->findOrFail($inventoryItemId);
            $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItemId);
            
            // Determine movement type
            $movementType = $quantity > 0 
                ? InventoryMovement::TYPE_ADJUSTMENT_IN 
                : InventoryMovement::TYPE_ADJUSTMENT_OUT;
            
            // Create movement record
            $movement = InventoryMovement::createMovement(
                $inventoryItemId,
                $movementType,
                abs($quantity),
                $unitCost,
                $reason,
                null,
                null,
                $notes
            );
            
            // Update stock level
            $stockLevel->updateFromMovement($movement);
            
            // Check for low stock and send notification if needed
            if ($stockLevel->isLowStock()) {
                dispatch(new SendLowStockNotification($inventoryItem, $stockLevel));
            }
            
            return [
                'movement' => $movement,
                'stock_level' => $stockLevel->fresh(),
                'inventory_item' => $inventoryItem,
            ];
        });
    }

    /**
     * Process stock deduction for a sale.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Stock deductions are per inventory_item per store.
     * 
     * @param string $inventoryItemId The inventory item ID
     * @param float $quantity Quantity sold
     * @param string|null $orderId Optional order ID
     * @return array
     */
    public function processSale(string $inventoryItemId, float $quantity, ?string $orderId = null): array
    {
        return DB::transaction(function () use ($inventoryItemId, $quantity, $orderId) {
            $inventoryItem = InventoryItem::where('track_stock', true)->findOrFail($inventoryItemId);
            $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItemId);
            
            // Check if enough stock is available
            if ($stockLevel->available_stock < $quantity) {
                throw new Exception("Insufficient stock. Available: {$stockLevel->available_stock}, Required: {$quantity}");
            }
            
            // Create sale movement
            $movement = InventoryMovement::createMovement(
                $inventoryItemId,
                InventoryMovement::TYPE_SALE,
                $quantity,
                $stockLevel->average_cost,
                'Sale transaction',
                'App\Models\Order',
                $orderId
            );
            
            // Update stock level
            $stockLevel->updateFromMovement($movement);
            
            // Check for low stock and send notification if needed
            if ($stockLevel->isLowStock()) {
                dispatch(new SendLowStockNotification($inventoryItem, $stockLevel));
            }
            
            return [
                'movement' => $movement,
                'stock_level' => $stockLevel->fresh(),
                'inventory_item' => $inventoryItem,
            ];
        });
    }

    /**
     * Process stock increase for a purchase.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Stock increases are per inventory_item per store.
     * 
     * @param string $inventoryItemId The inventory item ID
     * @param float $quantity Quantity purchased
     * @param float $unitCost Unit cost
     * @param string|null $referenceId Optional reference ID (e.g., purchase order ID)
     * @param string|null $notes Optional notes
     * @return array
     */
    public function processPurchase(
        string $inventoryItemId,
        float $quantity,
        float $unitCost,
        ?string $referenceId = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($inventoryItemId, $quantity, $unitCost, $referenceId, $notes) {
            $inventoryItem = InventoryItem::where('track_stock', true)->findOrFail($inventoryItemId);
            $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItemId);
            
            // Create purchase movement
            $movement = InventoryMovement::createMovement(
                $inventoryItemId,
                InventoryMovement::TYPE_PURCHASE,
                $quantity,
                $unitCost,
                'Purchase order',
                'App\Models\PurchaseOrder',
                $referenceId,
                $notes
            );
            
            // Update stock level
            $stockLevel->updateFromMovement($movement);
            
            return [
                'movement' => $movement,
                'stock_level' => $stockLevel->fresh(),
                'inventory_item' => $inventoryItem,
            ];
        });
    }

    /**
     * Get inventory valuation report.
     * 
     * NOTE: Now returns valuation per inventory_item (not per product).
     */
    public function getInventoryValuation(): array
    {
        $stockLevels = StockLevel::with('inventoryItem:id,name,sku')
            ->whereHas('inventoryItem', function ($q) {
                $q->where('track_stock', true);
            })
            ->where('current_stock', '>', 0)
            ->get();

        $totalValue = $stockLevels->sum('total_value');
        $totalItems = $stockLevels->sum('current_stock');

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'items_count' => $stockLevels->count(),
            'stock_levels' => $stockLevels,
            'valuation_date' => now(),
        ];
    }

    /**
     * Get stock movement summary for a period.
     * 
     * NOTE: Now returns summary per inventory_item (not per product).
     */
    public function getMovementSummary(string $dateFrom, string $dateTo): array
    {
        $movements = InventoryMovement::with('inventoryItem:id,name,sku')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $stockInTypes = [
            InventoryMovement::TYPE_PURCHASE,
            InventoryMovement::TYPE_ADJUSTMENT_IN,
            InventoryMovement::TYPE_TRANSFER_IN,
            InventoryMovement::TYPE_RETURN,
        ];

        $stockOutTypes = [
            InventoryMovement::TYPE_SALE,
            InventoryMovement::TYPE_ADJUSTMENT_OUT,
            InventoryMovement::TYPE_TRANSFER_OUT,
            InventoryMovement::TYPE_WASTE,
        ];

        $summary = [
            'total_movements' => $movements->count(),
            'stock_in' => $movements->whereIn('type', $stockInTypes)->sum('quantity'),
            'stock_out' => $movements->whereIn('type', $stockOutTypes)->sum('quantity'),
            'by_type' => $movements->groupBy('type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'quantity' => $group->sum('quantity'),
                    'value' => $group->sum('total_cost'),
                ];
            }),
            'by_inventory_item' => $movements->groupBy('inventory_item_id')->map(function ($group) {
                $inventoryItem = $group->first()->inventoryItem;
                return [
                    'inventory_item_name' => $inventoryItem?->name,
                    'inventory_item_sku' => $inventoryItem?->sku,
                    'movements_count' => $group->count(),
                    'net_quantity' => $group->sum(function ($movement) {
                        return $movement->getSignedQuantity();
                    }),
                ];
            }),
        ];

        return $summary;
    }

    /**
     * Get low stock inventory items.
     * 
     * NOTE: Now returns low stock per inventory_item (not per product).
     */
    public function getLowStockItems(): array
    {
        $lowStockItems = StockLevel::with('inventoryItem:id,name,sku,min_stock_level')
            ->whereHas('inventoryItem', function ($q) {
                $q->where('track_stock', true);
            })
            ->get()
            ->filter(function ($level) {
                return $level->isLowStock();
            });

        return [
            'count' => $lowStockItems->count(),
            'inventory_items' => $lowStockItems->values(),
        ];
    }

    /**
     * @deprecated Use getLowStockItems() instead. Stock is now tracked per inventory_item, not per product.
     */
    public function getLowStockProducts(): array
    {
        throw new Exception('getLowStockProducts() is deprecated. Use getLowStockItems() instead.');
    }

    /**
     * Reserve stock for an order.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Expected format: [['inventory_item_id' => '...', 'quantity' => 1.5], ...]
     */
    public function reserveStock(array $items): array
    {
        $reservations = [];
        $errors = [];

        DB::transaction(function () use ($items, &$reservations, &$errors) {
            foreach ($items as $item) {
                $inventoryItemId = $item['inventory_item_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
                
                if (!$inventoryItemId) {
                    $errors[] = [
                        'inventory_item_id' => null,
                        'message' => 'inventory_item_id is required',
                    ];
                    continue;
                }
                
                $inventoryItem = InventoryItem::where('track_stock', true)->find($inventoryItemId);
                
                if (!$inventoryItem) {
                    $errors[] = [
                        'inventory_item_id' => $inventoryItemId,
                        'message' => 'Inventory item not found or not tracking stock',
                    ];
                    continue;
                }
                
                $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItemId);
                
                if ($stockLevel->reserveStock((float) $quantity)) {
                    $reservations[] = [
                        'inventory_item_id' => $inventoryItemId,
                        'quantity' => $quantity,
                        'reserved' => true,
                    ];
                } else {
                    $errors[] = [
                        'inventory_item_id' => $inventoryItemId,
                        'inventory_item_name' => $inventoryItem->name,
                        'requested' => $quantity,
                        'available' => $stockLevel->available_stock,
                        'message' => 'Insufficient stock available',
                    ];
                }
            }
            
            // If there are any errors, rollback the transaction
            if (!empty($errors)) {
                throw new Exception('Stock reservation failed for some items');
            }
        });

        return [
            'reservations' => $reservations,
            'errors' => $errors,
        ];
    }

    /**
     * Release reserved stock.
     * 
     * NOTE: Now accepts inventory_item_id (not product_id).
     * Expected format: [['inventory_item_id' => '...', 'quantity' => 1.5], ...]
     */
    public function releaseReservedStock(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $inventoryItemId = $item['inventory_item_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
                
                if (!$inventoryItemId) {
                    continue;
                }
                
                $stockLevel = StockLevel::where('inventory_item_id', $inventoryItemId)->first();
                
                if ($stockLevel) {
                    $stockLevel->releaseReservedStock((float) $quantity);
                }
            }
        });
    }
}