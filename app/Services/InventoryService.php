<?php

namespace App\Services;

use App\Models\Product;
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
     */
    public function adjustStock(
        string $productId,
        int $quantity,
        string $reason,
        ?float $unitCost = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($productId, $quantity, $reason, $unitCost, $notes) {
            $product = Product::where('track_inventory', true)->findOrFail($productId);
            $stockLevel = StockLevel::getOrCreateForProduct($productId);
            
            // Determine movement type
            $movementType = $quantity > 0 
                ? InventoryMovement::TYPE_ADJUSTMENT_IN 
                : InventoryMovement::TYPE_ADJUSTMENT_OUT;
            
            // Create movement record
            $movement = InventoryMovement::createMovement(
                $productId,
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
                dispatch(new SendLowStockNotification($product, $stockLevel));
            }
            
            return [
                'movement' => $movement,
                'stock_level' => $stockLevel->fresh(),
                'product' => $product,
            ];
        });
    }

    /**
     * Process stock deduction for a sale.
     */
    public function processSale(string $productId, int $quantity, ?string $orderId = null): array
    {
        return DB::transaction(function () use ($productId, $quantity, $orderId) {
            $product = Product::where('track_inventory', true)->findOrFail($productId);
            $stockLevel = StockLevel::getOrCreateForProduct($productId);
            
            // Check if enough stock is available
            if ($stockLevel->available_stock < $quantity) {
                throw new Exception("Insufficient stock. Available: {$stockLevel->available_stock}, Required: {$quantity}");
            }
            
            // Create sale movement
            $movement = InventoryMovement::createMovement(
                $productId,
                InventoryMovement::TYPE_SALE,
                $quantity,
                $stockLevel->average_cost,
                'Sale transaction',
                'App\Models\Order',
                $orderId
            );
            
            // Update stock level
            $stockLevel->updateFromMovement($movement);
            
            // Calculate COGS using CogsService
            $cogsService = app(CogsService::class);
            $cogsRecord = $cogsService->calculateCogs(
                $productId,
                $quantity,
                CogsHistory::METHOD_WEIGHTED_AVERAGE,
                $orderId
            );
            
            // Check for low stock and send notification if needed
            if ($stockLevel->isLowStock()) {
                dispatch(new SendLowStockNotification($product, $stockLevel));
            }
            
            return [
                'movement' => $movement,
                'stock_level' => $stockLevel->fresh(),
                'cogs' => $cogsRecord,
                'product' => $product,
            ];
        });
    }

    /**
     * Process stock increase for a purchase.
     */
    public function processPurchase(
        string $productId,
        int $quantity,
        float $unitCost,
        ?string $referenceId = null,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($productId, $quantity, $unitCost, $referenceId, $notes) {
            $product = Product::where('track_inventory', true)->findOrFail($productId);
            $stockLevel = StockLevel::getOrCreateForProduct($productId);
            
            // Create purchase movement
            $movement = InventoryMovement::createMovement(
                $productId,
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
                'product' => $product,
            ];
        });
    }

    /**
     * Get inventory valuation report.
     */
    public function getInventoryValuation(): array
    {
        $stockLevels = StockLevel::with('product:id,name,sku')
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true);
            })
            ->where('current_stock', '>', 0)
            ->get();

        $totalValue = $stockLevels->sum('total_value');
        $totalItems = $stockLevels->sum('current_stock');

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'products_count' => $stockLevels->count(),
            'stock_levels' => $stockLevels,
            'valuation_date' => now(),
        ];
    }

    /**
     * Get stock movement summary for a period.
     */
    public function getMovementSummary(string $dateFrom, string $dateTo): array
    {
        $movements = InventoryMovement::with('product:id,name,sku')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $summary = [
            'total_movements' => $movements->count(),
            'stock_in' => $movements->where('type', 'in')->sum('quantity'),
            'stock_out' => $movements->where('type', 'out')->sum('quantity'),
            'by_type' => $movements->groupBy('type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'quantity' => $group->sum('quantity'),
                    'value' => $group->sum('total_cost'),
                ];
            }),
            'by_product' => $movements->groupBy('product_id')->map(function ($group) {
                $product = $group->first()->product;
                return [
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
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
     * Get low stock products.
     */
    public function getLowStockProducts(): array
    {
        $lowStockProducts = StockLevel::with('product:id,name,sku,min_stock_level')
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true)
                  ->whereColumn('stock_levels.current_stock', '<=', 'products.min_stock_level');
            })
            ->get();

        return [
            'count' => $lowStockProducts->count(),
            'products' => $lowStockProducts,
        ];
    }

    /**
     * Reserve stock for an order.
     */
    public function reserveStock(array $items): array
    {
        $reservations = [];
        $errors = [];

        DB::transaction(function () use ($items, &$reservations, &$errors) {
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                
                $product = Product::where('track_inventory', true)->find($productId);
                
                if (!$product) {
                    continue; // Skip non-tracked products
                }
                
                $stockLevel = StockLevel::getOrCreateForProduct($productId);
                
                if ($stockLevel->reserveStock($quantity)) {
                    $reservations[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'reserved' => true,
                    ];
                } else {
                    $errors[] = [
                        'product_id' => $productId,
                        'product_name' => $product->name,
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
     */
    public function releaseReservedStock(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                
                $stockLevel = StockLevel::where('product_id', $productId)->first();
                
                if ($stockLevel) {
                    $stockLevel->releaseReservedStock($quantity);
                }
            }
        });
    }
}