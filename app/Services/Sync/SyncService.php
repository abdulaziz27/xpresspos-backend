<?php

namespace App\Services\Sync;

use App\Models\SyncHistory;
use App\Models\Order;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Member;
use App\Models\Expense;
use App\Services\Concerns\ResolvesStoreContext;
use App\Services\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SyncService
{
    use ResolvesStoreContext;

    protected StoreContext $storeContext;

    protected ConflictResolver $conflictResolver;
    protected IdempotencyService $idempotencyService;

    public function __construct(ConflictResolver $conflictResolver, IdempotencyService $idempotencyService, StoreContext $storeContext)
    {
        $this->conflictResolver = $conflictResolver;
        $this->idempotencyService = $idempotencyService;
        $this->storeContext = $storeContext;
    }

    protected function storeId(array $context = [], bool $allowNull = false): ?string
    {
        return $this->resolveStoreId($context, $allowNull);
    }

    /**
     * Process a sync operation.
     */
    public function processSync(
        string $idempotencyKey,
        string $syncType,
        string $operation,
        string $entityType,
        array $data,
        ?string $entityId = null,
        ?string $timestamp = null
    ): array {
        // Check idempotency
        if ($this->idempotencyService->isDuplicate($idempotencyKey)) {
            $existingSync = SyncHistory::findByIdempotencyKey($idempotencyKey);
            return [
                'status' => 'duplicate',
                'message' => 'Already processed',
                'entity_id' => $existingSync->entity_id,
            ];
        }

        // Create sync history record
        $syncHistory = SyncHistory::createSync(
            $idempotencyKey,
            $syncType,
            $operation,
            $entityType,
            $data,
            $entityId
        );

        try {
            $syncHistory->update(['status' => SyncHistory::STATUS_PROCESSING]);

            // Validate data integrity
            $this->validateSyncData($syncType, $operation, $data);

            // Process based on sync type and operation
            $result = $this->processSyncByType($syncType, $operation, $data, $entityId, $timestamp);

            if (isset($result['conflicts']) && !empty($result['conflicts'])) {
                // Handle conflicts
                $syncHistory->markConflicted($result['conflicts']);
                return [
                    'status' => 'conflict',
                    'message' => 'Conflicts detected',
                    'conflicts' => $result['conflicts'],
                    'entity_id' => $result['entity_id'] ?? null,
                ];
            }

            // Mark as completed
            $syncHistory->markCompleted($result['entity_id']);
            
            Log::info('Sync completed successfully', [
                'idempotency_key' => $idempotencyKey,
                'sync_type' => $syncType,
                'operation' => $operation,
                'entity_id' => $result['entity_id'],
            ]);

            return [
                'status' => 'completed',
                'message' => 'Sync completed successfully',
                'entity_id' => $result['entity_id'],
            ];

        } catch (\Exception $e) {
            $syncHistory->markFailed($e->getMessage());
            
            Log::error('Sync failed', [
                'idempotency_key' => $idempotencyKey,
                'sync_type' => $syncType,
                'operation' => $operation,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Process sync based on type.
     */
    protected function processSyncByType(
        string $syncType,
        string $operation,
        array $data,
        ?string $entityId,
        ?string $timestamp
    ): array {
        return match ($syncType) {
            SyncHistory::TYPE_ORDER => $this->processOrderSync($operation, $data, $entityId, $timestamp),
            SyncHistory::TYPE_INVENTORY => $this->processInventorySync($operation, $data, $entityId, $timestamp),
            SyncHistory::TYPE_PAYMENT => $this->processPaymentSync($operation, $data, $entityId, $timestamp),
            SyncHistory::TYPE_PRODUCT => $this->processProductSync($operation, $data, $entityId, $timestamp),
            SyncHistory::TYPE_MEMBER => $this->processMemberSync($operation, $data, $entityId, $timestamp),
            SyncHistory::TYPE_EXPENSE => $this->processExpenseSync($operation, $data, $entityId, $timestamp),
            default => throw new \InvalidArgumentException("Unsupported sync type: {$syncType}"),
        };
    }

    /**
     * Process order sync.
     */
    protected function processOrderSync(string $operation, array $data, ?string $entityId, ?string $timestamp): array
    {
        switch ($operation) {
            case SyncHistory::OPERATION_CREATE:
                return $this->createOrder($data, $timestamp);
            
            case SyncHistory::OPERATION_UPDATE:
                return $this->updateOrder($entityId, $data, $timestamp);
            
            case SyncHistory::OPERATION_DELETE:
                return $this->deleteOrder($entityId);
            
            default:
                throw new \InvalidArgumentException("Unsupported operation: {$operation}");
        }
    }

    /**
     * Create order from sync data.
     */
    protected function createOrder(array $data, ?string $timestamp): array
    {
        $storeId = $this->storeId($data);

        // Check for existing order with same order_number
        $existingOrder = Order::where('store_id', $storeId)
            ->where('order_number', $data['order_number'])
            ->first();

        if ($existingOrder) {
            // Check for conflicts
            $conflicts = $this->conflictResolver->detectOrderConflicts($existingOrder, $data, $timestamp);
            if (!empty($conflicts)) {
                return [
                    'conflicts' => $conflicts,
                    'entity_id' => $existingOrder->id,
                ];
            }
        }

            DB::beginTransaction();
            try {
                // Create order
                $order = Order::create([
                    'store_id' => $storeId,
                    'user_id' => $data['user_id'] ?? Auth::id(),
                    'member_id' => $data['member_id'] ?? null,
                    'table_id' => $data['table_id'] ?? null,
                'order_number' => $data['order_number'],
                'status' => $data['status'] ?? 'draft',
                'subtotal' => $data['subtotal'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'service_charge' => $data['service_charge'] ?? 0,
                'total_amount' => $data['total_amount'] ?? 0,
                'payment_method' => $data['payment_method'] ?? null,
                'total_items' => $data['total_items'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'completed_at' => isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            ]);

            // Create order items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $order->items()->create([
                        'product_id' => $itemData['product_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['total_price'],
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return ['entity_id' => $order->id];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update order from sync data.
     */
    protected function updateOrder(string $entityId, array $data, ?string $timestamp): array
    {
        $storeId = $this->storeId($data, true);

        $order = Order::query()
            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
            ->findOrFail($entityId);

        // Check for conflicts
        $conflicts = $this->conflictResolver->detectOrderConflicts($order, $data, $timestamp);
        if (!empty($conflicts)) {
            return [
                'conflicts' => $conflicts,
                'entity_id' => $order->id,
            ];
        }

        DB::beginTransaction();
        try {
            $order->update([
                'status' => $data['status'] ?? $order->status,
                'subtotal' => $data['subtotal'] ?? $order->subtotal,
                'tax_amount' => $data['tax_amount'] ?? $order->tax_amount,
                'discount_amount' => $data['discount_amount'] ?? $order->discount_amount,
                'service_charge' => $data['service_charge'] ?? $order->service_charge,
                'total_amount' => $data['total_amount'] ?? $order->total_amount,
                'payment_method' => $data['payment_method'] ?? $order->payment_method,
                'total_items' => $data['total_items'] ?? $order->total_items,
                'notes' => $data['notes'] ?? $order->notes,
                'completed_at' => isset($data['completed_at']) ? 
                    Carbon::parse($data['completed_at']) : $order->completed_at,
            ]);

            DB::commit();

            return ['entity_id' => $order->id];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete order.
     */
    protected function deleteOrder(string $entityId): array
    {
        $storeId = $this->storeId();

        $order = Order::where('store_id', $storeId)
            ->findOrFail($entityId);

        // Check if order can be deleted
        if ($order->status === 'completed') {
            throw new \InvalidArgumentException('Cannot delete completed order');
        }

        $order->delete();

        return ['entity_id' => $entityId];
    }

    /**
     * Process inventory sync.
     */
    protected function processInventorySync(string $operation, array $data, ?string $entityId, ?string $timestamp): array
    {
        switch ($operation) {
            case SyncHistory::OPERATION_CREATE:
                return $this->createInventoryMovement($data, $timestamp);
            
            default:
                throw new \InvalidArgumentException("Unsupported inventory operation: {$operation}");
        }
    }

    /**
     * Create inventory movement from sync data.
     */
    protected function createInventoryMovement(array $data, ?string $timestamp): array
    {
        $storeId = $this->storeId($data);

        // Check for duplicate movement
        $existingMovement = InventoryMovement::where('store_id', $storeId)
            ->where('product_id', $data['product_id'])
            ->where('type', $data['type'])
            ->where('quantity', $data['quantity'])
            ->where('reference_type', $data['reference_type'] ?? null)
            ->where('reference_id', $data['reference_id'] ?? null)
            ->where('created_at', '>=', now()->subMinutes(5)) // Within 5 minutes
            ->first();

        if ($existingMovement) {
            return ['entity_id' => $existingMovement->id];
        }

        $movement = InventoryMovement::create([
            'store_id' => $storeId,
            'product_id' => $data['product_id'],
            'user_id' => $data['user_id'] ?? Auth::id(),
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? null,
            'total_cost' => $data['total_cost'] ?? null,
            'reason' => $data['reason'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Update product stock level
        $product = Product::find($data['product_id']);
        if ($product && $product->track_inventory) {
            $signedQuantity = $movement->getSignedQuantity();
            $product->increment('stock_quantity', $signedQuantity);
        }

        return ['entity_id' => $movement->id];
    }

    /**
     * Process payment sync.
     */
    protected function processPaymentSync(string $operation, array $data, ?string $entityId, ?string $timestamp): array
    {
        switch ($operation) {
            case SyncHistory::OPERATION_CREATE:
                return $this->createPayment($data, $timestamp);
            
            case SyncHistory::OPERATION_UPDATE:
                return $this->updatePayment($entityId, $data, $timestamp);
            
            default:
                throw new \InvalidArgumentException("Unsupported payment operation: {$operation}");
        }
    }

    /**
     * Create payment from sync data.
     */
    protected function createPayment(array $data, ?string $timestamp): array
    {
        $storeId = $this->storeId($data);

        $payment = Payment::create([
            'store_id' => $storeId,
            'order_id' => $data['order_id'],
            'user_id' => $data['user_id'] ?? Auth::id(),
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'] ?? $data['method'] ?? null,
            'status' => $data['status'] ?? 'completed',
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return ['entity_id' => $payment->id];
    }

    /**
     * Update payment from sync data.
     */
    protected function updatePayment(string $entityId, array $data, ?string $timestamp): array
    {
        $storeId = $this->storeId($data, true);

        $payment = Payment::query()
            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
            ->findOrFail($entityId);

        $payment->update([
            'status' => $data['status'] ?? $payment->status,
            'reference_number' => $data['reference_number'] ?? $payment->reference_number,
            'notes' => $data['notes'] ?? $payment->notes,
            'payment_method' => $data['payment_method'] ?? $data['method'] ?? $payment->payment_method,
        ]);

        return ['entity_id' => $payment->id];
    }

    /**
     * Process product sync.
     */
    protected function processProductSync(string $operation, array $data, ?string $entityId, ?string $timestamp): array
    {
        // Implementation for product sync operations
        throw new \BadMethodCallException('Product sync not yet implemented');
    }

    /**
     * Process member sync.
     */
    protected function processMemberSync(string $operation, array $data, ?string $entityId, ?string $timestamp): array
    {
        // Implementation for member sync operations
        throw new \BadMethodCallException('Member sync not yet implemented');
    }

    /**
     * Process expense sync.
     */
    protected function processExpenseSync(string $operation, array $data, ?string $entityId, ?string $timestamp): array
    {
        // Implementation for expense sync operations
        throw new \BadMethodCallException('Expense sync not yet implemented');
    }

    /**
     * Validate sync data integrity.
     */
    protected function validateSyncData(string $syncType, string $operation, array $data): void
    {
        // Basic validation based on sync type
        switch ($syncType) {
            case SyncHistory::TYPE_ORDER:
                $this->validateOrderData($operation, $data);
                break;
            
            case SyncHistory::TYPE_INVENTORY:
                $this->validateInventoryData($operation, $data);
                break;
            
            case SyncHistory::TYPE_PAYMENT:
                $this->validatePaymentData($operation, $data);
                break;
        }
    }

    /**
     * Validate order data.
     */
    protected function validateOrderData(string $operation, array $data): void
    {
        if ($operation === SyncHistory::OPERATION_CREATE) {
            if (empty($data['order_number'])) {
                throw new \InvalidArgumentException('Order number is required');
            }
            if (empty($data['total_amount']) || $data['total_amount'] < 0) {
                throw new \InvalidArgumentException('Valid total amount is required');
            }
        }
    }

    /**
     * Validate inventory data.
     */
    protected function validateInventoryData(string $operation, array $data): void
    {
        if ($operation === SyncHistory::OPERATION_CREATE) {
            if (empty($data['product_id'])) {
                throw new \InvalidArgumentException('Product ID is required');
            }
            if (empty($data['type'])) {
                throw new \InvalidArgumentException('Movement type is required');
            }
            if (!isset($data['quantity']) || $data['quantity'] <= 0) {
                throw new \InvalidArgumentException('Valid quantity is required');
            }
        }
    }

    /**
     * Validate payment data.
     */
    protected function validatePaymentData(string $operation, array $data): void
    {
        if ($operation === SyncHistory::OPERATION_CREATE) {
            if (empty($data['order_id'])) {
                throw new \InvalidArgumentException('Order ID is required');
            }
            if (empty($data['amount']) || $data['amount'] <= 0) {
                throw new \InvalidArgumentException('Valid amount is required');
            }
            if (empty($data['method'])) {
                throw new \InvalidArgumentException('Payment method is required');
            }
        }
    }
}
