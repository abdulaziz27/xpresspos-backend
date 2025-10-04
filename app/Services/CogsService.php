<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\CogsHistory;
use App\Models\InventoryMovement;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class CogsService
{
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
        
        // Use inventory-based calculation
        return CogsHistory::calculateCogs($productId, $quantitySold, $method, $orderId);
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
        $recipe->load(['items.ingredient']);
        
        $totalIngredientCost = 0;
        $costBreakdown = [];
        
        foreach ($recipe->items as $item) {
            $ingredient = $item->ingredient;
            $quantityNeeded = ($item->quantity / $recipe->yield_quantity) * $quantitySold;
            
            // Get current cost of ingredient
            $ingredientStockLevel = StockLevel::where('product_id', $ingredient->id)->first();
            $unitCost = $ingredientStockLevel ? $ingredientStockLevel->average_cost : $ingredient->cost_price;
            
            $ingredientTotalCost = $quantityNeeded * $unitCost;
            $totalIngredientCost += $ingredientTotalCost;
            
            $costBreakdown[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->name,
                'quantity_needed' => $quantityNeeded,
                'unit_cost' => $unitCost,
                'total_cost' => $ingredientTotalCost,
            ];
        }
        
        $unitCogs = $quantitySold > 0 ? $totalIngredientCost / $quantitySold : 0;
        
        return CogsHistory::create([
            'store_id' => auth()->user()->store_id,
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
        $cogsRecords = CogsHistory::with('product:id,name,sku')
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

        $results = DB::select($query, [$dateFrom, $dateTo, auth()->user()->store_id]);

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
        $recipes = Recipe::where('is_active', true)->get();
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
     */
    public function getInventoryValuationComparison(): array
    {
        $stockLevels = StockLevel::with('product:id,name,sku')
            ->whereHas('product', function ($q) {
                $q->where('track_inventory', true);
            })
            ->where('current_stock', '>', 0)
            ->get();

        $valuations = [
            'weighted_average' => 0,
            'fifo' => 0,
            'lifo' => 0,
        ];

        foreach ($stockLevels as $stockLevel) {
            $product = $stockLevel->product;
            $quantity = $stockLevel->current_stock;

            // Weighted Average (current method)
            $valuations['weighted_average'] += $quantity * $stockLevel->average_cost;

            // FIFO valuation
            $fifoMovements = InventoryMovement::where('product_id', $product->id)
                ->stockIn()
                ->where('unit_cost', '>', 0)
                ->orderBy('created_at')
                ->get();

            $remainingQuantity = $quantity;
            $fifoValue = 0;

            foreach ($fifoMovements as $movement) {
                if ($remainingQuantity <= 0) break;
                
                $quantityFromBatch = min($remainingQuantity, $movement->quantity);
                $fifoValue += $quantityFromBatch * $movement->unit_cost;
                $remainingQuantity -= $quantityFromBatch;
            }

            $valuations['fifo'] += $fifoValue;

            // LIFO valuation
            $lifoMovements = InventoryMovement::where('product_id', $product->id)
                ->stockIn()
                ->where('unit_cost', '>', 0)
                ->orderByDesc('created_at')
                ->get();

            $remainingQuantity = $quantity;
            $lifoValue = 0;

            foreach ($lifoMovements as $movement) {
                if ($remainingQuantity <= 0) break;
                
                $quantityFromBatch = min($remainingQuantity, $movement->quantity);
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
            'products_count' => $stockLevels->count(),
        ];
    }
}