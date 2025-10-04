<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\PlanLimitValidationService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Log order creation for audit trail
        Log::info('Order created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'store_id' => $order->store_id,
            'total_amount' => $order->total_amount,
            'status' => $order->status,
        ]);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if order status changed to completed
        if ($order->isDirty('status') && $order->status === 'completed') {
            $this->handleOrderCompletion($order);
        }
    }

    /**
     * Handle order completion and increment transaction usage.
     */
    private function handleOrderCompletion(Order $order): void
    {
        try {
            $store = $order->store;
            
            if (!$store) {
                Log::warning('Order completed but store not found', [
                    'order_id' => $order->id,
                    'store_id' => $order->store_id,
                ]);
                return;
            }
            
            $subscription = $store->activeSubscription;
            
            if (!$subscription) {
                Log::info('Order completed but store has no active subscription', [
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                ]);
                return;
            }
            
            // Process inventory deduction for tracked products
            $this->processInventoryDeduction($order);
            
            // Increment transaction usage
            $planLimitService = app(PlanLimitValidationService::class);
            $result = $planLimitService->incrementUsage($store, 'transactions', 1);
            
            if ($result['success']) {
                Log::info('Transaction usage incremented for completed order', [
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    'old_usage' => $result['old_usage'],
                    'new_usage' => $result['new_usage'],
                    'usage_percentage' => $result['usage_percentage'],
                    'quota_exceeded' => $result['quota_exceeded'],
                ]);
                
                // Log if quota was exceeded
                if ($result['quota_exceeded']) {
                    Log::warning('Store has exceeded transaction quota', [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'current_usage' => $result['new_usage'],
                        'plan' => $subscription->plan->name,
                    ]);
                }
            } else {
                Log::error('Failed to increment transaction usage', [
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    'error' => $result['message'],
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error handling order completion for usage tracking', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process inventory deduction for order items.
     */
    private function processInventoryDeduction(Order $order): void
    {
        try {
            $inventoryService = app(InventoryService::class);
            
            // Load order items with products
            $order->load(['items.product']);
            
            foreach ($order->items as $item) {
                $product = $item->product;
                
                // Skip if product doesn't track inventory
                if (!$product || !$product->track_inventory) {
                    continue;
                }
                
                // Process sale for inventory tracking
                try {
                    $inventoryService->processSale(
                        $product->id,
                        $item->quantity,
                        $order->id
                    );
                    
                    Log::info('Inventory deducted for order item', [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item->quantity,
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('Failed to deduct inventory for order item', [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item->quantity,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error processing inventory deduction for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        Log::info('Order deleted', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'store_id' => $order->store_id,
        ]);
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        Log::info('Order restored', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'store_id' => $order->store_id,
        ]);
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        Log::info('Order force deleted', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'store_id' => $order->store_id,
        ]);
    }
}