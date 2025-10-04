<?php

namespace App\Services\Sync;

use App\Models\SyncHistory;
use App\Models\Order;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Member;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ConflictResolver
{
    /**
     * Detect conflicts for order sync.
     */
    public function detectOrderConflicts(Order $existingOrder, array $syncData, ?string $timestamp): array
    {
        $conflicts = [];
        $syncTimestamp = $timestamp ? Carbon::parse($timestamp) : now();

        // Check if existing order was modified after sync timestamp
        if ($existingOrder->updated_at > $syncTimestamp) {
            $conflicts[] = [
                'type' => 'timestamp_conflict',
                'field' => 'updated_at',
                'server_value' => $existingOrder->updated_at->toISOString(),
                'client_value' => $syncTimestamp->toISOString(),
                'message' => 'Server record is newer than sync data',
            ];
        }

        // Check for field-level conflicts
        $fieldsToCheck = ['status', 'total_amount', 'payment_method', 'notes'];
        foreach ($fieldsToCheck as $field) {
            if (isset($syncData[$field]) && $existingOrder->{$field} != $syncData[$field]) {
                $conflicts[] = [
                    'type' => 'field_conflict',
                    'field' => $field,
                    'server_value' => $existingOrder->{$field},
                    'client_value' => $syncData[$field],
                    'message' => "Field '{$field}' has different values",
                ];
            }
        }

        // Check for status transition conflicts
        if (isset($syncData['status'])) {
            $statusConflict = $this->checkOrderStatusConflict($existingOrder->status, $syncData['status']);
            if ($statusConflict) {
                $conflicts[] = $statusConflict;
            }
        }

        return $conflicts;
    }

    /**
     * Check for order status transition conflicts.
     */
    protected function checkOrderStatusConflict(string $currentStatus, string $newStatus): ?array
    {
        // Define valid status transitions
        $validTransitions = [
            'draft' => ['open', 'cancelled'],
            'open' => ['completed', 'cancelled'],
            'completed' => [], // Completed orders cannot be changed
            'cancelled' => [], // Cancelled orders cannot be changed
        ];

        if (!isset($validTransitions[$currentStatus])) {
            return [
                'type' => 'status_conflict',
                'field' => 'status',
                'server_value' => $currentStatus,
                'client_value' => $newStatus,
                'message' => "Unknown current status: {$currentStatus}",
            ];
        }

        if (!in_array($newStatus, $validTransitions[$currentStatus])) {
            return [
                'type' => 'status_conflict',
                'field' => 'status',
                'server_value' => $currentStatus,
                'client_value' => $newStatus,
                'message' => "Invalid status transition from {$currentStatus} to {$newStatus}",
            ];
        }

        return null;
    }

    /**
     * Detect conflicts for inventory movement sync.
     */
    public function detectInventoryConflicts(Product $product, array $syncData, ?string $timestamp): array
    {
        $conflicts = [];
        $syncTimestamp = $timestamp ? Carbon::parse($timestamp) : now();

        // Check if product stock was modified after sync timestamp
        if ($product->updated_at > $syncTimestamp) {
            $conflicts[] = [
                'type' => 'timestamp_conflict',
                'field' => 'stock_quantity',
                'server_value' => $product->stock_quantity,
                'client_timestamp' => $syncTimestamp->toISOString(),
                'server_timestamp' => $product->updated_at->toISOString(),
                'message' => 'Product stock was modified after sync timestamp',
            ];
        }

        // Check for negative stock conflicts
        if ($syncData['type'] === InventoryMovement::TYPE_SALE) {
            $newStock = $product->stock_quantity - $syncData['quantity'];
            if ($newStock < 0) {
                $conflicts[] = [
                    'type' => 'stock_conflict',
                    'field' => 'stock_quantity',
                    'server_value' => $product->stock_quantity,
                    'required_quantity' => $syncData['quantity'],
                    'resulting_stock' => $newStock,
                    'message' => 'Insufficient stock for this operation',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Detect conflicts for payment sync.
     */
    public function detectPaymentConflicts(Payment $existingPayment, array $syncData, ?string $timestamp): array
    {
        $conflicts = [];
        $syncTimestamp = $timestamp ? Carbon::parse($timestamp) : now();

        // Check timestamp conflict
        if ($existingPayment->updated_at > $syncTimestamp) {
            $conflicts[] = [
                'type' => 'timestamp_conflict',
                'field' => 'updated_at',
                'server_value' => $existingPayment->updated_at->toISOString(),
                'client_value' => $syncTimestamp->toISOString(),
                'message' => 'Server payment record is newer than sync data',
            ];
        }

        // Check for status conflicts
        if (isset($syncData['status']) && $existingPayment->status !== $syncData['status']) {
            // Check if status change is valid
            if ($existingPayment->status === 'completed' && $syncData['status'] !== 'completed') {
                $conflicts[] = [
                    'type' => 'status_conflict',
                    'field' => 'status',
                    'server_value' => $existingPayment->status,
                    'client_value' => $syncData['status'],
                    'message' => 'Cannot change status of completed payment',
                ];
            }
        }

        // Check for amount conflicts
        if (isset($syncData['amount']) && $existingPayment->amount != $syncData['amount']) {
            $conflicts[] = [
                'type' => 'field_conflict',
                'field' => 'amount',
                'server_value' => $existingPayment->amount,
                'client_value' => $syncData['amount'],
                'message' => 'Payment amount differs between server and client',
            ];
        }

        return $conflicts;
    }

    /**
     * Resolve a conflict using the specified strategy.
     */
    public function resolveConflict(SyncHistory $syncRecord, string $resolution, ?array $mergeData = null): array
    {
        Log::info('Resolving sync conflict', [
            'idempotency_key' => $syncRecord->idempotency_key,
            'sync_type' => $syncRecord->sync_type,
            'resolution' => $resolution,
        ]);

        return match ($resolution) {
            'use_local' => $this->useLocalData($syncRecord),
            'use_server' => $this->useServerData($syncRecord),
            'merge' => $this->mergeData($syncRecord, $mergeData),
            default => throw new \InvalidArgumentException("Invalid resolution strategy: {$resolution}"),
        };
    }

    /**
     * Use local (client) data to resolve conflict.
     */
    protected function useLocalData(SyncHistory $syncRecord): array
    {
        DB::beginTransaction();
        try {
            $result = $this->applyLocalChanges($syncRecord);
            $syncRecord->markCompleted($result['entity_id']);
            
            DB::commit();

            Log::info('Conflict resolved using local data', [
                'idempotency_key' => $syncRecord->idempotency_key,
                'entity_id' => $result['entity_id'],
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Use server data to resolve conflict (essentially ignore client changes).
     */
    protected function useServerData(SyncHistory $syncRecord): array
    {
        // Find the existing server entity
        $entityId = $this->findExistingEntity($syncRecord);
        
        if (!$entityId) {
            throw new \RuntimeException('Server entity not found for conflict resolution');
        }

        // Mark sync as completed without making changes
        $syncRecord->markCompleted($entityId);

        Log::info('Conflict resolved using server data', [
            'idempotency_key' => $syncRecord->idempotency_key,
            'entity_id' => $entityId,
        ]);

        return ['entity_id' => $entityId];
    }

    /**
     * Merge local and server data to resolve conflict.
     */
    protected function mergeData(SyncHistory $syncRecord, ?array $mergeData): array
    {
        if (!$mergeData) {
            throw new \InvalidArgumentException('Merge data is required for merge resolution');
        }

        DB::beginTransaction();
        try {
            $result = $this->applyMergedChanges($syncRecord, $mergeData);
            $syncRecord->markCompleted($result['entity_id']);
            
            DB::commit();

            Log::info('Conflict resolved using merged data', [
                'idempotency_key' => $syncRecord->idempotency_key,
                'entity_id' => $result['entity_id'],
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply local changes to resolve conflict.
     */
    protected function applyLocalChanges(SyncHistory $syncRecord): array
    {
        return match ($syncRecord->sync_type) {
            SyncHistory::TYPE_ORDER => $this->applyLocalOrderChanges($syncRecord),
            SyncHistory::TYPE_INVENTORY => $this->applyLocalInventoryChanges($syncRecord),
            SyncHistory::TYPE_PAYMENT => $this->applyLocalPaymentChanges($syncRecord),
            default => throw new \InvalidArgumentException("Unsupported sync type for local resolution: {$syncRecord->sync_type}"),
        };
    }

    /**
     * Apply local order changes.
     */
    protected function applyLocalOrderChanges(SyncHistory $syncRecord): array
    {
        $data = $syncRecord->payload;
        
        if ($syncRecord->operation === SyncHistory::OPERATION_CREATE) {
            // Find existing order by order_number and update it
            $order = Order::where('store_id', $syncRecord->store_id)
                ->where('order_number', $data['order_number'])
                ->first();

            if ($order) {
                $order->update([
                    'status' => $data['status'] ?? $order->status,
                    'subtotal' => $data['subtotal'] ?? $order->subtotal,
                    'tax_amount' => $data['tax_amount'] ?? $order->tax_amount,
                    'discount_amount' => $data['discount_amount'] ?? $order->discount_amount,
                    'service_charge' => $data['service_charge'] ?? $order->service_charge,
                    'total_amount' => $data['total_amount'] ?? $order->total_amount,
                    'payment_method' => $data['payment_method'] ?? $order->payment_method,
                    'notes' => $data['notes'] ?? $order->notes,
                ]);
            } else {
                // Create new order
                $order = Order::create([
                    'store_id' => $syncRecord->store_id,
                    'user_id' => $data['user_id'] ?? $syncRecord->user_id,
                    'order_number' => $data['order_number'],
                    'status' => $data['status'] ?? 'draft',
                    'subtotal' => $data['subtotal'] ?? 0,
                    'tax_amount' => $data['tax_amount'] ?? 0,
                    'discount_amount' => $data['discount_amount'] ?? 0,
                    'service_charge' => $data['service_charge'] ?? 0,
                    'total_amount' => $data['total_amount'] ?? 0,
                    'payment_method' => $data['payment_method'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            return ['entity_id' => $order->id];
        }

        throw new \InvalidArgumentException("Unsupported order operation for local resolution: {$syncRecord->operation}");
    }

    /**
     * Apply local inventory changes.
     */
    protected function applyLocalInventoryChanges(SyncHistory $syncRecord): array
    {
        $data = $syncRecord->payload;

        if ($syncRecord->operation === SyncHistory::OPERATION_CREATE) {
            // Create the inventory movement regardless of conflicts
            $movement = InventoryMovement::create([
                'store_id' => $syncRecord->store_id,
                'product_id' => $data['product_id'],
                'user_id' => $data['user_id'] ?? $syncRecord->user_id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? null,
                'total_cost' => $data['total_cost'] ?? null,
                'reason' => $data['reason'] ?? 'Conflict resolution - local data',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Update product stock
            $product = Product::find($data['product_id']);
            if ($product && $product->track_inventory) {
                $signedQuantity = $movement->getSignedQuantity();
                $product->increment('stock_quantity', $signedQuantity);
            }

            return ['entity_id' => $movement->id];
        }

        throw new \InvalidArgumentException("Unsupported inventory operation for local resolution: {$syncRecord->operation}");
    }

    /**
     * Apply local payment changes.
     */
    protected function applyLocalPaymentChanges(SyncHistory $syncRecord): array
    {
        $data = $syncRecord->payload;

        if ($syncRecord->operation === SyncHistory::OPERATION_CREATE) {
            $payment = Payment::create([
                'store_id' => $syncRecord->store_id,
                'order_id' => $data['order_id'],
                'user_id' => $data['user_id'] ?? $syncRecord->user_id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'status' => $data['status'] ?? 'completed',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return ['entity_id' => $payment->id];
        }

        if ($syncRecord->operation === SyncHistory::OPERATION_UPDATE && $syncRecord->entity_id) {
            $payment = Payment::where('store_id', $syncRecord->store_id)
                ->findOrFail($syncRecord->entity_id);

            $payment->update([
                'status' => $data['status'] ?? $payment->status,
                'reference_number' => $data['reference_number'] ?? $payment->reference_number,
                'notes' => $data['notes'] ?? $payment->notes,
            ]);

            return ['entity_id' => $payment->id];
        }

        throw new \InvalidArgumentException("Unsupported payment operation for local resolution: {$syncRecord->operation}");
    }

    /**
     * Apply merged changes to resolve conflict.
     */
    protected function applyMergedChanges(SyncHistory $syncRecord, array $mergeData): array
    {
        // This is a simplified implementation
        // In practice, you would implement sophisticated merging logic
        // based on the specific entity type and business rules

        $entityId = $this->findExistingEntity($syncRecord);
        if (!$entityId) {
            // If no existing entity, create with merged data
            return $this->createWithMergedData($syncRecord, $mergeData);
        }

        // Update existing entity with merged data
        return $this->updateWithMergedData($syncRecord, $entityId, $mergeData);
    }

    /**
     * Create entity with merged data.
     */
    protected function createWithMergedData(SyncHistory $syncRecord, array $mergeData): array
    {
        // Merge original payload with merge data
        $finalData = array_merge($syncRecord->payload, $mergeData);

        // Create entity based on type
        return match ($syncRecord->sync_type) {
            SyncHistory::TYPE_ORDER => $this->createOrderWithData($syncRecord, $finalData),
            SyncHistory::TYPE_PAYMENT => $this->createPaymentWithData($syncRecord, $finalData),
            default => throw new \InvalidArgumentException("Unsupported sync type for merge creation: {$syncRecord->sync_type}"),
        };
    }

    /**
     * Update entity with merged data.
     */
    protected function updateWithMergedData(SyncHistory $syncRecord, string $entityId, array $mergeData): array
    {
        return match ($syncRecord->sync_type) {
            SyncHistory::TYPE_ORDER => $this->updateOrderWithMergedData($entityId, $mergeData),
            SyncHistory::TYPE_PAYMENT => $this->updatePaymentWithMergedData($entityId, $mergeData),
            default => throw new \InvalidArgumentException("Unsupported sync type for merge update: {$syncRecord->sync_type}"),
        };
    }

    /**
     * Create order with merged data.
     */
    protected function createOrderWithData(SyncHistory $syncRecord, array $data): array
    {
        $order = Order::create([
            'store_id' => $syncRecord->store_id,
            'user_id' => $data['user_id'] ?? $syncRecord->user_id,
            'order_number' => $data['order_number'],
            'status' => $data['status'] ?? 'draft',
            'subtotal' => $data['subtotal'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'service_charge' => $data['service_charge'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'payment_method' => $data['payment_method'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return ['entity_id' => $order->id];
    }

    /**
     * Create payment with merged data.
     */
    protected function createPaymentWithData(SyncHistory $syncRecord, array $data): array
    {
        $payment = Payment::create([
            'store_id' => $syncRecord->store_id,
            'order_id' => $data['order_id'],
            'user_id' => $data['user_id'] ?? $syncRecord->user_id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'status' => $data['status'] ?? 'completed',
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return ['entity_id' => $payment->id];
    }

    /**
     * Update order with merged data.
     */
    protected function updateOrderWithMergedData(string $entityId, array $mergeData): array
    {
        $order = Order::findOrFail($entityId);
        $order->update($mergeData);

        return ['entity_id' => $order->id];
    }

    /**
     * Update payment with merged data.
     */
    protected function updatePaymentWithMergedData(string $entityId, array $mergeData): array
    {
        $payment = Payment::findOrFail($entityId);
        $payment->update($mergeData);

        return ['entity_id' => $payment->id];
    }

    /**
     * Find existing entity ID for a sync record.
     */
    protected function findExistingEntity(SyncHistory $syncRecord): ?string
    {
        if ($syncRecord->entity_id) {
            return $syncRecord->entity_id;
        }

        // Try to find by unique identifiers in payload
        $data = $syncRecord->payload;

        return match ($syncRecord->sync_type) {
            SyncHistory::TYPE_ORDER => $this->findExistingOrder($syncRecord->store_id, $data),
            SyncHistory::TYPE_PAYMENT => $this->findExistingPayment($syncRecord->store_id, $data),
            default => null,
        };
    }

    /**
     * Find existing order.
     */
    protected function findExistingOrder(string $storeId, array $data): ?string
    {
        if (isset($data['order_number'])) {
            $order = Order::where('store_id', $storeId)
                ->where('order_number', $data['order_number'])
                ->first();
            return $order?->id;
        }

        return null;
    }

    /**
     * Find existing payment.
     */
    protected function findExistingPayment(string $storeId, array $data): ?string
    {
        if (isset($data['reference_number']) && $data['reference_number']) {
            $payment = Payment::where('store_id', $storeId)
                ->where('reference_number', $data['reference_number'])
                ->first();
            return $payment?->id;
        }

        return null;
    }
}