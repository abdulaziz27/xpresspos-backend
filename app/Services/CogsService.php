<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CogsHistory;
use App\Models\CogsDetail;
use App\Models\InventoryMovement;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Services\Concerns\ResolvesStoreContext;
use App\Services\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CogsService
{
    use ResolvesStoreContext;

    protected StoreContext $storeContext;

    public function __construct(StoreContext $storeContext)
    {
        $this->storeContext = $storeContext;
    }

    /**
     * Calculate COGS for a product sale using different methods.
     */
    public function calculateCogs(
        string $productId,
        int $quantitySold,
        string $method = CogsHistory::METHOD_WEIGHTED_AVERAGE,
        ?string $orderId = null
    ): CogsHistory {
        $product = Product::findOrFail($productId);
        
        // Check if product has a recipe
        $recipe = $product->recipes()->where('is_active', true)->first();
        
        if ($recipe) {
            return $this->calculateRecipeBasedCogs($product, $recipe, $quantitySold, $orderId);
        }
        
        // For non-recipe products, COGS calculation via stock_levels/product_id is deprecated
        // Use recipe-based calculation or redesign for inventory-item-based COGS in Wave 3
        throw new \Exception(
            'COGS calculation for non-recipe products via stock_levels/product_id is deprecated due to inventory refactor. ' .
            'Products without recipes should use recipe-based COGS calculations. ' .
            'Full inventory-item-based COGS will be redesigned in Wave 3.'
        );
    }

    /**
     * Calculate COGS based on recipe ingredients.
     */
    protected function calculateRecipeBasedCogs(
        Product $product,
        Recipe $recipe,
        int $quantitySold,
        ?string $orderId = null
    ): CogsHistory {
        $recipe->load(['items.inventoryItem']);
        
        $totalIngredientCost = 0;
        $costBreakdown = [];
        
        foreach ($recipe->items as $item) {
            // Recipe items now use inventory_item_id, not product_id
            $inventoryItem = $item->inventoryItem;
            $quantityNeeded = ($item->quantity / $recipe->yield_quantity) * $quantitySold;
            
            // Get current cost of inventory item
            $storeId = $this->resolveStoreId();
            $ingredientStockLevel = $storeId 
                ? StockLevel::where('inventory_item_id', $inventoryItem->id)
                    ->where('store_id', $storeId)
                    ->first()
                : null;
            $unitCost = $ingredientStockLevel 
                ? $ingredientStockLevel->average_cost 
                : ($inventoryItem->default_cost ?? 0);
            
            $ingredientTotalCost = $quantityNeeded * $unitCost;
            $totalIngredientCost += $ingredientTotalCost;
            
            $costBreakdown[] = [
                'inventory_item_id' => $inventoryItem->id,
                'inventory_item_name' => $inventoryItem->name,
                'quantity_needed' => $quantityNeeded,
                'unit_cost' => $unitCost,
                'total_cost' => $ingredientTotalCost,
            ];
        }
        
        $unitCogs = $quantitySold > 0 ? $totalIngredientCost / $quantitySold : 0;
        
        $storeId = $this->resolveStoreId(['store_id' => $product->store_id], true) ?? $product->store_id;

        return CogsHistory::create([
            'store_id' => $storeId,
            'product_id' => $product->id,
            'order_id' => $orderId,
            'quantity_sold' => $quantitySold,
            'unit_cost' => $unitCogs,
            'total_cogs' => $totalIngredientCost,
            'calculation_method' => 'recipe_based',
            'cost_breakdown' => $costBreakdown,
        ]);
    }

    /**
     * Get COGS summary for a period.
     */
    public function getCogsSummary(string $dateFrom, string $dateTo): array
    {
        $storeId = $this->resolveStoreId();

        $cogsRecords = CogsHistory::with('product:id,name,sku')
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $summary = [
            'total_cogs' => $cogsRecords->sum('total_cogs'),
            'total_quantity_sold' => $cogsRecords->sum('quantity_sold'),
            'average_unit_cost' => 0,
            'by_method' => $cogsRecords->groupBy('calculation_method')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_cogs' => $group->sum('total_cogs'),
                    'total_quantity' => $group->sum('quantity_sold'),
                ];
            }),
            'by_product' => $cogsRecords->groupBy('product_id')->map(function ($group) {
                $product = $group->first()->product;
                return [
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'total_cogs' => $group->sum('total_cogs'),
                    'total_quantity' => $group->sum('quantity_sold'),
                    'average_unit_cost' => $group->sum('quantity_sold') > 0 
                        ? $group->sum('total_cogs') / $group->sum('quantity_sold') 
                        : 0,
                ];
            }),
        ];

        // Calculate overall average unit cost
        if ($summary['total_quantity_sold'] > 0) {
            $summary['average_unit_cost'] = $summary['total_cogs'] / $summary['total_quantity_sold'];
        }

        return $summary;
    }

    /**
     * Get profit margin analysis.
     */
    public function getProfitMarginAnalysis(string $dateFrom, string $dateTo): array
    {
        $storeId = $this->resolveStoreId();

        $query = "
            SELECT 
                p.id,
                p.name,
                p.sku,
                p.price as selling_price,
                SUM(ch.quantity_sold) as total_quantity_sold,
                SUM(ch.total_cogs) as total_cogs,
                AVG(ch.unit_cost) as average_unit_cost,
                (p.price - AVG(ch.unit_cost)) as unit_profit,
                ((p.price - AVG(ch.unit_cost)) / p.price * 100) as profit_margin_percentage
            FROM products p
            INNER JOIN cogs_history ch ON p.id = ch.product_id
            WHERE ch.created_at BETWEEN ? AND ?
            AND p.store_id = ?
            GROUP BY p.id, p.name, p.sku, p.price
            ORDER BY profit_margin_percentage DESC
        ";

        $results = DB::select($query, [$dateFrom, $dateTo, $storeId]);

        $totalRevenue = 0;
        $totalCogs = 0;

        foreach ($results as $result) {
            $revenue = $result->total_quantity_sold * $result->selling_price;
            $totalRevenue += $revenue;
            $totalCogs += $result->total_cogs;
            
            $result->total_revenue = $revenue;
            $result->total_profit = $revenue - $result->total_cogs;
        }

        $overallProfitMargin = $totalRevenue > 0 ? (($totalRevenue - $totalCogs) / $totalRevenue * 100) : 0;

        return [
            'products' => $results,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cogs' => $totalCogs,
                'total_profit' => $totalRevenue - $totalCogs,
                'overall_profit_margin' => $overallProfitMargin,
            ],
        ];
    }

    /**
     * Update recipe costs based on current ingredient prices.
     */
    public function updateAllRecipeCosts(): array
    {
        $storeId = $this->resolveStoreId([], true);

        $recipes = Recipe::query()
            ->where('is_active', true)
            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
            ->get();
        $updated = 0;

        foreach ($recipes as $recipe) {
            $oldCost = $recipe->total_cost;
            $recipe->recalculateCosts();
            
            if ($recipe->total_cost != $oldCost) {
                $updated++;
            }
        }

        return [
            'total_recipes' => $recipes->count(),
            'updated_recipes' => $updated,
        ];
    }

    /**
     * Get inventory valuation using different COGS methods.
     * 
     * NOTE: Now returns valuation per inventory_item (not per product).
     * 
     * @deprecated This method needs redesign for inventory-item-based COGS in Wave 3.
     * For now, returns valuation per inventory_item using weighted average only.
     */
    public function getInventoryValuationComparison(): array
    {
        $storeId = $this->resolveStoreId();
        
        $stockLevels = StockLevel::with('inventoryItem:id,name,sku')
            ->whereHas('inventoryItem', function ($q) {
                $q->where('track_stock', true);
            })
            ->where('current_stock', '>', 0)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->get();

        $valuations = [
            'weighted_average' => 0,
            'fifo' => 0,
            'lifo' => 0,
        ];

        foreach ($stockLevels as $stockLevel) {
            $inventoryItem = $stockLevel->inventoryItem;
            $quantity = $stockLevel->current_stock;

            // Weighted Average (current method)
            $valuations['weighted_average'] += $quantity * $stockLevel->average_cost;

            // FIFO valuation
            $fifoMovements = InventoryMovement::where('inventory_item_id', $inventoryItem->id)
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->stockIn()
                ->where('unit_cost', '>', 0)
                ->orderBy('created_at')
                ->get();

            $remainingQuantity = $quantity;
            $fifoValue = 0;

            foreach ($fifoMovements as $movement) {
                if ($remainingQuantity <= 0) break;
                
                $quantityFromBatch = min($remainingQuantity, (float) $movement->quantity);
                $fifoValue += $quantityFromBatch * $movement->unit_cost;
                $remainingQuantity -= $quantityFromBatch;
            }

            $valuations['fifo'] += $fifoValue;

            // LIFO valuation
            $lifoMovements = InventoryMovement::where('inventory_item_id', $inventoryItem->id)
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->stockIn()
                ->where('unit_cost', '>', 0)
                ->orderByDesc('created_at')
                ->get();

            $remainingQuantity = $quantity;
            $lifoValue = 0;

            foreach ($lifoMovements as $movement) {
                if ($remainingQuantity <= 0) break;
                
                $quantityFromBatch = min($remainingQuantity, (float) $movement->quantity);
                $lifoValue += $quantityFromBatch * $movement->unit_cost;
                $remainingQuantity -= $quantityFromBatch;
            }

            $valuations['lifo'] += $lifoValue;
        }

        return [
            'valuations' => $valuations,
            'differences' => [
                'fifo_vs_weighted_avg' => $valuations['fifo'] - $valuations['weighted_average'],
                'lifo_vs_weighted_avg' => $valuations['lifo'] - $valuations['weighted_average'],
                'fifo_vs_lifo' => $valuations['fifo'] - $valuations['lifo'],
            ],
            'items_count' => $stockLevels->count(),
        ];
    }

    /**
     * Process COGS for a completed order.
     * 
     * This method:
     * 1. Validates order is eligible for COGS processing
     * 2. For each order item with active recipe:
     *    - Calculates inventory consumption based on recipe
     *    - Creates inventory movements (type 'sale')
     *    - Creates COGS history and details
     * 
     * NOTE: Uses recipe-based default cost (not per-lot/FIFO).
     * lot_id in cogs_details will be null for Wave 3.
     * 
     * @param Order $order The completed order
     * @throws \Exception If order is not eligible or processing fails
     */
    public function processOrder(Order $order): void
    {
        // Pre-condition checks
        if ($order->status !== 'completed') {
            throw new \Exception("Order {$order->id} is not completed. Current status: {$order->status}");
        }

        if (!$order->store_id) {
            throw new \Exception("Order {$order->id} has no store_id");
        }

        // Idempotency check: skip if already processed
        if (CogsHistory::where('order_id', $order->id)->exists()) {
            Log::info("Order {$order->id} already has COGS history, skipping processing");
            return;
        }

        DB::transaction(function () use ($order) {
            // Load order items with products
            $order->load('items.product');

            // Group order items by product
            $productGroups = $order->items->groupBy('product_id');

            // Process each product group
            foreach ($productGroups as $productId => $orderItems) {
                $product = $orderItems->first()->product;

                // Skip if product doesn't track inventory
                if (!$product || !$product->track_inventory) {
                    Log::debug("Skipping product {$productId}: track_inventory = false");
                    continue;
                }

                // Get active recipe with items and inventory items
                $activeRecipe = $product->getActiveRecipe();
                if (!$activeRecipe) {
                    Log::debug("Skipping product {$productId}: no active recipe");
                    continue;
                }

                // Load recipe items with inventory items
                $activeRecipe->load('items.inventoryItem');
                
                if ($activeRecipe->items->isEmpty()) {
                    Log::debug("Skipping product {$productId}: recipe has no items");
                    continue;
                }

                // Calculate total quantity sold for this product in this order
                $totalQuantitySold = $orderItems->sum('quantity');

                // Build consumption map: inventory_item_id => total consumption data
                $consumptionMap = [];
                $totalCogs = 0;

                // Process each order item for this product
                foreach ($orderItems as $orderItem) {
                    // Calculate multiplier: how many recipe yields needed
                    $yield = $activeRecipe->yield_quantity > 0 ? $activeRecipe->yield_quantity : 1;
                    $multiplier = $orderItem->quantity / $yield;

                    // Process each recipe item
                    foreach ($activeRecipe->items as $recipeItem) {
                        $inventoryItem = $recipeItem->inventoryItem;
                        if (!$inventoryItem) {
                            continue;
                        }

                        // Calculate consumption for this order item
                        $baseQty = $recipeItem->quantity;
                        $consumedQty = $baseQty * $multiplier;
                        $unitCost = $recipeItem->unit_cost;
                        $lineTotalCost = $consumedQty * $unitCost;

                        // Skip if consumed_qty or cost is 0
                        if ($consumedQty <= 0 || $lineTotalCost <= 0) {
                            continue;
                        }

                        // Accumulate in consumption map
                        if (!isset($consumptionMap[$inventoryItem->id])) {
                            $consumptionMap[$inventoryItem->id] = [
                                'inventory_item' => $inventoryItem,
                                'total_consumed_qty' => 0,
                                'total_cost' => 0,
                                'unit_cost' => $unitCost,
                                'details' => [], // For cogs_details
                            ];
                        }

                        $consumptionMap[$inventoryItem->id]['total_consumed_qty'] += $consumedQty;
                        $consumptionMap[$inventoryItem->id]['total_cost'] += $lineTotalCost;
                        $totalCogs += $lineTotalCost;

                        // Store detail for cogs_details
                        $consumptionMap[$inventoryItem->id]['details'][] = [
                            'order_item_id' => $orderItem->id,
                            'quantity' => $consumedQty,
                            'unit_cost' => $unitCost,
                            'total_cost' => $lineTotalCost,
                        ];
                    }
                }

                // Create inventory movements for each inventory item
                foreach ($consumptionMap as $inventoryItemId => $consumption) {
                    $inventoryItem = $consumption['inventory_item'];
                    $totalQty = $consumption['total_consumed_qty'];
                    $totalCost = $consumption['total_cost'];
                    $avgUnitCost = $totalQty > 0 ? $totalCost / $totalQty : $consumption['unit_cost'];

                    // Create inventory movement (type 'sale')
                    $movement = InventoryMovement::create([
                        'store_id' => $order->store_id,
                        'inventory_item_id' => $inventoryItemId,
                        'user_id' => $order->user_id,
                        'type' => InventoryMovement::TYPE_SALE,
                        'quantity' => $totalQty,
                        'unit_cost' => $avgUnitCost,
                        'total_cost' => $totalCost,
                        'reason' => 'Order sale',
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'notes' => "Auto COGS via recipe for order {$order->order_number}",
                    ]);

                    // Update stock level from movement
                    $stockLevel = StockLevel::getOrCreateForInventoryItem($inventoryItemId, $order->store_id);
                    $stockLevel->updateFromMovement($movement);
                }

                // Create COGS history (summary per product)
                $unitCogs = $totalQuantitySold > 0 ? $totalCogs / $totalQuantitySold : 0;

                $cogsHistory = CogsHistory::create([
                    'store_id' => $order->store_id,
                    'product_id' => $productId,
                    'order_id' => $order->id,
                    'quantity_sold' => $totalQuantitySold,
                    'unit_cost' => $unitCogs,
                    'total_cogs' => $totalCogs,
                    'calculation_method' => CogsHistory::METHOD_WEIGHTED_AVERAGE, // Using as default for recipe-based
                    'cost_breakdown' => array_map(function ($item) {
                        return [
                            'inventory_item_id' => $item['inventory_item']->id,
                            'inventory_item_name' => $item['inventory_item']->name,
                            'quantity' => $item['total_consumed_qty'],
                            'total_cost' => $item['total_cost'],
                        ];
                    }, array_values($consumptionMap)),
                ]);

                // Create COGS details (granular per order_item and inventory_item)
                foreach ($consumptionMap as $inventoryItemId => $consumption) {
                    foreach ($consumption['details'] as $detail) {
                        CogsDetail::create([
                            'cogs_history_id' => $cogsHistory->id,
                            'order_item_id' => $detail['order_item_id'],
                            'inventory_item_id' => $inventoryItemId,
                            'lot_id' => null, // Wave 3: not using lots yet
                            'quantity' => $detail['quantity'],
                            'unit_cost' => $detail['unit_cost'],
                            'total_cost' => $detail['total_cost'],
                        ]);
                    }
                }

                Log::info("COGS processed for order {$order->id}, product {$productId}", [
                    'quantity_sold' => $totalQuantitySold,
                    'total_cogs' => $totalCogs,
                    'inventory_items_count' => count($consumptionMap),
                ]);
            }
        });
    }

    /**
     * Process COGS for an order by ID.
     * 
     * @param string $orderId The order ID
     * @throws \Exception If order not found or processing fails
     */
    public function processOrderById(string $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $this->processOrder($order);
    }
}
