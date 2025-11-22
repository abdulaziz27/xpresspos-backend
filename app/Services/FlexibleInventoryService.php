<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlexibleInventoryService
{
    /**
     * Process inventory based on order event and store settings.
     */
    public function processOrderInventory(Order $order, string $event): void
    {
        $store = $order->store;
        $inventorySettings = $store->settings['inventory_settings'] ?? [];
        $deductionTiming = $inventorySettings['deduction_timing'] ?? 'order_confirmed';
        
        // Only process if timing matches event
        if ($deductionTiming !== $event) {
            Log::info("Skipping inventory processing", [
                'order_id' => $order->id,
                'event' => $event,
                'expected_timing' => $deductionTiming
            ]);
            return;
        }
        
        Log::info("Processing inventory for order", [
            'order_id' => $order->id,
            'event' => $event,
            'operation_mode' => $order->operation_mode,
            'payment_mode' => $order->payment_mode
        ]);
        
        foreach ($order->items as $item) {
            $product = $item->product;
            
            if (!$product || !$product->track_inventory) {
                continue;
            }
            
            try {
                switch ($event) {
                    case 'order_created':
                        // Only for direct payment modes
                        if ($order->payment_mode === 'direct') {
                            $this->reserveStock($product, $item->quantity, $order);
                        }
                        break;
                        
                    case 'order_confirmed':
                        $this->reserveStock($product, $item->quantity, $order);
                        break;
                        
                    case 'order_ready':
                        // For takeaway/delivery, deduct when ready
                        if (in_array($order->operation_mode, ['takeaway', 'delivery'])) {
                            $this->deductStock($product, $item->quantity, $order);
                        }
                        break;
                        
                    case 'order_served':
                        // For dine-in, deduct when served
                        if ($order->operation_mode === 'dine_in') {
                            $this->deductStock($product, $item->quantity, $order);
                        }
                        break;
                        
                    case 'order_completed':
                        // Final deduction if not done earlier
                        $this->finalizeStock($product, $item->quantity, $order);
                        break;
                        
                    case 'order_cancelled':
                        $this->restoreStock($product, $item->quantity, $order);
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Inventory processing failed", [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'event' => $event,
                    'error' => $e->getMessage()
                ]);
                
                // Don't throw exception, just log it
                // Order processing should continue
            }
        }
    }
    
    /**
     * Reserve stock for order.
     */
    private function reserveStock(Product $product, int $quantity, Order $order): void
    {
        DB::transaction(function () use ($product, $quantity, $order) {
            $product = $product->lockForUpdate();
            $store = $order->store;
            $allowNegative = $store->settings['inventory_settings']['allow_negative_stock'] ?? false;
            
            if ($product->stock < $quantity && !$allowNegative) {
                throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock}, Required: {$quantity}");
            }
            
            // Check if already reserved
            // NOTE: This service is deprecated - product-based inventory is no longer valid
            // TODO: Refactor to use inventory_item_id in Wave 3
            throw new \Exception(
                'FlexibleInventoryService::reserveStock() is deprecated. ' .
                'Product-based inventory operations are deprecated due to inventory refactor. ' .
                'Use InventoryService for inventory-item-based operations.'
            );
                
            if ($existingReservation) {
                Log::info("Stock already reserved", [
                    'product_id' => $product->id,
                    'order_id' => $order->id
                ]);
                return;
            }
            
            // Reserve stock
            $product->decrement('stock', $quantity);
            
            // Log movement
            InventoryMovement::create([
                'store_id' => $order->store_id,
                'product_id' => $product->id,
                'type' => 'reserved',
                'quantity' => -$quantity, // Negative for outgoing
                'unit_cost' => $product->cost_price ?? 0,
                'total_cost' => ($product->cost_price ?? 0) * $quantity,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'notes' => "Reserved for order {$order->order_number} ({$order->operation_mode})"
            ]);
            
            Log::info("Stock reserved", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'order_id' => $order->id,
                'remaining_stock' => $product->fresh()->stock
            ]);
        });
    }
    
    /**
     * Deduct stock (convert reservation to sale).
     */
    private function deductStock(Product $product, int $quantity, Order $order): void
    {
        DB::transaction(function () use ($product, $quantity, $order) {
            // Check if already deducted
            $existingDeduction = InventoryMovement::where('product_id', $product->id)
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('type', 'sale')
                ->first();
                
            if ($existingDeduction) {
                Log::info("Stock already deducted", [
                    'product_id' => $product->id,
                    'order_id' => $order->id
                ]);
                return;
            }
            
            // Log sale movement
            InventoryMovement::create([
                'store_id' => $order->store_id,
                'product_id' => $product->id,
                'type' => 'sale',
                'quantity' => -$quantity, // Negative for outgoing
                'unit_cost' => $product->cost_price ?? 0,
                'total_cost' => ($product->cost_price ?? 0) * $quantity,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'notes' => "Sold via order {$order->order_number} ({$order->operation_mode})"
            ]);
            
            Log::info("Stock deducted", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'order_id' => $order->id
            ]);
        });
    }
    
    /**
     * Finalize stock (ensure deduction is complete).
     */
    private function finalizeStock(Product $product, int $quantity, Order $order): void
    {
        // Check if sale movement exists
        $saleMovement = InventoryMovement::where('product_id', $product->id)
            ->where('reference_type', Order::class)
            ->where('reference_id', $order->id)
            ->where('type', 'sale')
            ->first();
            
        if (!$saleMovement) {
            // No sale movement yet, create it
            $this->deductStock($product, $quantity, $order);
        }
    }
    
    /**
     * Restore stock (for cancelled orders).
     */
    private function restoreStock(Product $product, int $quantity, Order $order): void
    {
        DB::transaction(function () use ($product, $quantity, $order) {
            $product = $product->lockForUpdate();
            
            // Find existing movements for this order
            $movements = InventoryMovement::where('product_id', $product->id)
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->whereIn('type', ['reserved', 'sale'])
                ->get();
                
            if ($movements->isEmpty()) {
                Log::info("No inventory movements to restore", [
                    'product_id' => $product->id,
                    'order_id' => $order->id
                ]);
                return;
            }
            
            // Restore stock
            $product->increment('stock', $quantity);
            
            // Log restoration
            InventoryMovement::create([
                'store_id' => $order->store_id,
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $quantity, // Positive for incoming
                'unit_cost' => $product->cost_price ?? 0,
                'total_cost' => ($product->cost_price ?? 0) * $quantity,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'notes' => "Restored from cancelled order {$order->order_number}"
            ]);
            
            Log::info("Stock restored", [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'order_id' => $order->id,
                'new_stock' => $product->fresh()->stock
            ]);
        });
    }
    
    /**
     * Get inventory status for order.
     */
    public function getOrderInventoryStatus(Order $order): array
    {
        $status = [];
        
        foreach ($order->items as $item) {
            $product = $item->product;
            
            if (!$product || !$product->track_inventory) {
                $status[$item->id] = [
                    'product_name' => $item->product_name,
                    'tracking' => false,
                    'status' => 'not_tracked'
                ];
                continue;
            }
            
            $movements = InventoryMovement::where('product_id', $product->id)
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->get();
                
            $hasReservation = $movements->where('type', 'reserved')->isNotEmpty();
            $hasSale = $movements->where('type', 'sale')->isNotEmpty();
            
            $inventoryStatus = 'pending';
            if ($hasSale) {
                $inventoryStatus = 'deducted';
            } elseif ($hasReservation) {
                $inventoryStatus = 'reserved';
            }
            
            $status[$item->id] = [
                'product_name' => $product->name,
                'tracking' => true,
                'status' => $inventoryStatus,
                'current_stock' => $product->stock,
                'quantity_ordered' => $item->quantity
            ];
        }
        
        return $status;
    }
}