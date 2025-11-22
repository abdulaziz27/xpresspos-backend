<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryMovement;

/**
 * @deprecated This service uses product_id for inventory operations which is no longer valid.
 * Stock is now tracked per inventory_item, not per product.
 * Use InventoryService instead for inventory-item-based operations.
 */
class FnBInventoryService
{
    /**
     * F&B specific inventory tracking
     * Simple approach: Product-level stock only
     */
    public function reduceStock(Product $product, int $quantity, string $reason = 'Sale'): bool
    {
        if (!$product->track_inventory) {
            return true; // No tracking needed for services/unlimited items
        }

        if ($product->stock < $quantity) {
            return false; // Insufficient stock
        }

        // Reduce stock
        $product->decrement('stock', $quantity);

        // Record movement
        $this->recordMovement($product, -$quantity, $reason);

        // Check if low stock alert needed
        if ($product->isLowStock()) {
            $this->triggerLowStockAlert($product);
        }

        return true;
    }

    /**
     * Add stock (for receiving inventory)
     */
    public function addStock(Product $product, int $quantity, string $reason = 'Restock'): void
    {
        if (!$product->track_inventory) {
            return;
        }

        $product->increment('stock', $quantity);
        $this->recordMovement($product, $quantity, $reason);
    }

    /**
     * Get F&B specific stock status
     */
    public function getStockStatus(Product $product): array
    {
        if (!$product->track_inventory) {
            return [
                'status' => 'unlimited',
                'message' => 'Stock not tracked',
                'color' => 'green'
            ];
        }

        $stock = $product->stock;
        $minLevel = $product->min_stock_level;

        if ($stock <= 0) {
            return [
                'status' => 'out_of_stock',
                'message' => 'Out of Stock',
                'color' => 'red',
                'stock' => $stock
            ];
        }

        if ($stock <= $minLevel) {
            return [
                'status' => 'low_stock',
                'message' => "Low Stock ({$stock} remaining)",
                'color' => 'orange',
                'stock' => $stock
            ];
        }

        return [
            'status' => 'in_stock',
            'message' => "In Stock ({$stock} available)",
            'color' => 'green',
            'stock' => $stock
        ];
    }

    /**
     * Get products that need restocking
     */
    public function getRestockNeeded(): array
    {
        $products = Product::where('track_inventory', true)
            ->whereColumn('stock', '<=', 'min_stock_level')
            ->with('category')
            ->get();

        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name,
                'current_stock' => $product->stock,
                'min_level' => $product->min_stock_level,
                'suggested_order' => $this->calculateSuggestedOrder($product),
                'priority' => $product->stock <= 0 ? 'urgent' : 'normal'
            ];
        })->toArray();
    }

    /**
     * F&B Daily inventory report
     * 
     * @deprecated This method uses product_id for inventory_movements which is no longer valid.
     * Use InventoryService::getMovementSummary() instead.
     */
    public function getDailyReport(): array
    {
        throw new \Exception(
            'FnBInventoryService::getDailyReport() is deprecated. ' .
            'Product-based inventory reports are deprecated due to inventory refactor. ' .
            'Use InventoryService::getMovementSummary() for inventory-item-based reports.'
        );
    }

    /**
     * @deprecated This method uses product_id for inventory_movements which is no longer valid.
     */
    private function recordMovement(Product $product, int $quantity, string $reason): void
    {
        throw new \Exception(
            'FnBInventoryService::recordMovement() is deprecated. ' .
            'Product-based inventory movements are deprecated. ' .
            'Use InventoryService for inventory-item-based operations.'
        );
    }

    private function triggerLowStockAlert(Product $product): void
    {
        // Simple notification - could be enhanced with email/SMS
        logger()->warning("Low stock alert: {$product->name} ({$product->stock} remaining)");
        
        // Could add:
        // - Email to manager
        // - WhatsApp notification
        // - Dashboard alert
        // - Auto-reorder trigger
    }

    private function calculateSuggestedOrder(Product $product): int
    {
        // Simple F&B logic: Order enough for 1 week
        // Based on average daily usage
        $avgDailyUsage = $this->getAverageDailyUsage($product);
        $daysToStock = 7; // 1 week
        
        return max($avgDailyUsage * $daysToStock, $product->min_stock_level * 2);
    }

    /**
     * @deprecated This method uses product_id for inventory_movements which is no longer valid.
     */
    private function getAverageDailyUsage(Product $product): int
    {
        throw new \Exception(
            'FnBInventoryService::getAverageDailyUsage() is deprecated. ' .
            'Product-based inventory calculations are deprecated. ' .
            'Use InventoryService for inventory-item-based operations.'
        );
    }
}