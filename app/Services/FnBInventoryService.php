<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryMovement;

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
     */
    public function getDailyReport(): array
    {
        $today = now()->startOfDay();
        
        $movements = InventoryMovement::where('created_at', '>=', $today)
            ->with('product')
            ->get()
            ->groupBy('product_id');

        $report = [];
        foreach ($movements as $productId => $productMovements) {
            $product = $productMovements->first()->product;
            $totalSold = $productMovements->where('quantity', '<', 0)->sum('quantity') * -1;
            $totalReceived = $productMovements->where('quantity', '>', 0)->sum('quantity');

            $report[] = [
                'product_name' => $product->name,
                'opening_stock' => $product->stock + $totalSold - $totalReceived,
                'received' => $totalReceived,
                'sold' => $totalSold,
                'closing_stock' => $product->stock,
                'revenue' => $totalSold * $product->price,
            ];
        }

        return $report;
    }

    private function recordMovement(Product $product, int $quantity, string $reason): void
    {
        InventoryMovement::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'type' => $quantity > 0 ? 'in' : 'out',
            'reason' => $reason,
            'user_id' => auth()->id(),
            'reference_type' => 'manual',
        ]);
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

    private function getAverageDailyUsage(Product $product): int
    {
        $last7Days = InventoryMovement::where('product_id', $product->id)
            ->where('quantity', '<', 0) // Only outgoing
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('quantity') * -1;

        return (int) ceil($last7Days / 7);
    }
}