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
        // Log order creation in debug mode only
        if (config('app.debug')) {
            Log::debug('Order created', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'store_id' => $order->store_id,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
            ]);
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Handle status changes
        if ($order->wasChanged('status')) {
            $this->handleStatusChange($order);
        }
    }
    
    /**
     * Handle order status changes.
     */
    private function handleStatusChange(Order $order): void
    {
        $oldStatus = $order->getOriginal('status');
        $newStatus = $order->status;
        
        // Log status changes only for important transitions or in debug mode
        if (config('app.debug') || $newStatus === 'completed' || $newStatus === 'cancelled') {
            Log::info('Order status changed', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'operation_mode' => $order->operation_mode,
                'payment_mode' => $order->payment_mode,
            ]);
        }
        
        // Process inventory based on new status
        $inventoryService = app(\App\Services\FlexibleInventoryService::class);
        
        switch ($newStatus) {
            case 'confirmed':
                $inventoryService->processOrderInventory($order, 'order_confirmed');
                break;
                
            case 'ready':
                $inventoryService->processOrderInventory($order, 'order_ready');
                break;
                
            case 'served':
                $inventoryService->processOrderInventory($order, 'order_served');
                break;
                
            case 'completed':
                $inventoryService->processOrderInventory($order, 'order_completed');
                $this->handleOrderCompletion($order);
                break;
                
            case 'cancelled':
                $inventoryService->processOrderInventory($order, 'order_cancelled');
                break;
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
            
            // Inventory is handled by handleStatusChange method
            
            // Increment transaction usage
            $planLimitService = app(PlanLimitValidationService::class);
            $result = $planLimitService->incrementUsage($store, 'transactions', 1);
            
            if ($result['success']) {
                // Only log in debug mode or when quota is close to limit
                if (config('app.debug') || $result['usage_percentage'] > 80) {
                    Log::info('Transaction usage incremented for completed order', [
                        'order_id' => $order->id,
                        'store_id' => $store->id,
                        'old_usage' => $result['old_usage'],
                        'new_usage' => $result['new_usage'],
                        'usage_percentage' => $result['usage_percentage'],
                        'quota_exceeded' => $result['quota_exceeded'],
                    ]);
                }
                
                // Always log if quota was exceeded (important!)
                if ($result['quota_exceeded']) {
                    Log::warning('Store has exceeded transaction quota', [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'current_usage' => $result['new_usage'],
                        'plan' => $subscription->plan->name,
                    ]);
                }
            } else {
                // Always log errors
                Log::error('Failed to increment transaction usage', [
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    'error' => $result['message'],
                ]);
            }
            
            // Process loyalty points for member
            $this->processLoyaltyPoints($order);
            
        } catch (\Exception $e) {
            Log::error('Error handling order completion for usage tracking', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process loyalty points for completed order.
     */
    private function processLoyaltyPoints(Order $order): void
    {
        // Only process if order has a member
        if (!$order->member_id) {
            return;
        }

        try {
            $loyaltyService = app(\App\Services\LoyaltyService::class);
            $loyaltyService->processOrderLoyalty($order);
            
            // Only log in debug mode - production logs would be too verbose
            if (config('app.debug')) {
                Log::info('Loyalty points processed successfully', [
                    'order_id' => $order->id,
                    'member_id' => $order->member_id,
                    'order_amount' => $order->total_amount,
                ]);
            }
            
        } catch (\Exception $e) {
            // Always log errors - these are important for debugging
            Log::error('Failed to process loyalty points for order', [
                'order_id' => $order->id,
                'member_id' => $order->member_id,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);
            
            // Don't throw exception - loyalty point failure shouldn't block order completion
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
