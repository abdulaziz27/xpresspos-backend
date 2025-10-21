<?php

namespace App\Services\Sync;

use App\Models\Product;
use App\Models\Order;
use App\Models\Member;
use App\Models\User;
use App\Services\Concerns\ResolvesStoreContext;
use App\Services\StoreContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncValidationService
{
    use ResolvesStoreContext;

    protected StoreContext $storeContext;

    public function __construct(StoreContext $storeContext)
    {
        $this->storeContext = $storeContext;
    }
    /**
     * Validate sync data based on type and operation.
     */
    public function validateSyncData(string $syncType, string $operation, array $data): void
    {
        $rules = $this->getValidationRules($syncType, $operation);
        
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Perform additional business logic validation
        $this->performBusinessValidation($syncType, $operation, $data);
    }

    /**
     * Get validation rules for sync data.
     */
    protected function getValidationRules(string $syncType, string $operation): array
    {
        return match ($syncType) {
            'order' => $this->getOrderValidationRules($operation),
            'inventory' => $this->getInventoryValidationRules($operation),
            'payment' => $this->getPaymentValidationRules($operation),
            'product' => $this->getProductValidationRules($operation),
            'member' => $this->getMemberValidationRules($operation),
            'expense' => $this->getExpenseValidationRules($operation),
            default => [],
        };
    }

    /**
     * Get order validation rules.
     */
    protected function getOrderValidationRules(string $operation): array
    {
        $baseRules = [
            'order_number' => 'required|string|max:50',
            'total_amount' => 'required|numeric|min:0',
            'status' => 'required|string|in:draft,open,completed,cancelled',
        ];

        if ($operation === 'create') {
            return array_merge($baseRules, [
                'user_id' => 'nullable|uuid|exists:users,id',
                'member_id' => 'nullable|uuid|exists:members,id',
                'table_id' => 'nullable|uuid|exists:tables,id',
                'subtotal' => 'required|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'service_charge' => 'nullable|numeric|min:0',
                'items' => 'nullable|array',
                'items.*.product_id' => 'required|uuid|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.total_price' => 'required|numeric|min:0',
            ]);
        }

        return $baseRules;
    }

    /**
     * Get inventory validation rules.
     */
    protected function getInventoryValidationRules(string $operation): array
    {
        if ($operation === 'create') {
            return [
                'product_id' => 'required|uuid|exists:products,id',
                'type' => 'required|string|in:sale,purchase,adjustment_in,adjustment_out,transfer_in,transfer_out,return,waste',
                'quantity' => 'required|integer|min:1',
                'unit_cost' => 'nullable|numeric|min:0',
                'total_cost' => 'nullable|numeric|min:0',
                'reason' => 'nullable|string|max:255',
                'reference_type' => 'nullable|string|max:100',
                'reference_id' => 'nullable|uuid',
                'notes' => 'nullable|string|max:1000',
            ];
        }

        return [];
    }

    /**
     * Get payment validation rules.
     */
    protected function getPaymentValidationRules(string $operation): array
    {
        $baseRules = [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:cash,card,qris,transfer,ewallet',
            'status' => 'required|string|in:pending,completed,failed,cancelled',
        ];

        if ($operation === 'create') {
            return array_merge($baseRules, [
                'order_id' => 'required|uuid|exists:orders,id',
                'user_id' => 'nullable|uuid|exists:users,id',
                'reference_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);
        }

        return $baseRules;
    }

    /**
     * Get product validation rules.
     */
    protected function getProductValidationRules(string $operation): array
    {
        if ($operation === 'create') {
            return [
                'name' => 'required|string|max:255',
                'sku' => 'nullable|string|max:100',
                'category_id' => 'required|uuid|exists:categories,id',
                'price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'description' => 'nullable|string|max:1000',
                'track_inventory' => 'boolean',
                'stock_quantity' => 'nullable|integer|min:0',
                'min_stock_level' => 'nullable|integer|min:0',
            ];
        }

        return [
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'cost_price' => 'sometimes|numeric|min:0',
            'description' => 'sometimes|string|max:1000',
            'stock_quantity' => 'sometimes|integer|min:0',
        ];
    }

    /**
     * Get member validation rules.
     */
    protected function getMemberValidationRules(string $operation): array
    {
        if ($operation === 'create') {
            return [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'date_of_birth' => 'nullable|date|before:today',
                'gender' => 'nullable|string|in:male,female,other',
                'address' => 'nullable|string|max:500',
                'loyalty_points' => 'nullable|integer|min:0',
            ];
        }

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
        ];
    }

    /**
     * Get expense validation rules.
     */
    protected function getExpenseValidationRules(string $operation): array
    {
        if ($operation === 'create') {
            return [
                'amount' => 'required|numeric|min:0.01',
                'category' => 'required|string|max:100',
                'description' => 'required|string|max:255',
                'date' => 'required|date',
                'receipt_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ];
        }

        return [
            'amount' => 'sometimes|numeric|min:0.01',
            'category' => 'sometimes|string|max:100',
            'description' => 'sometimes|string|max:255',
            'notes' => 'sometimes|string|max:500',
        ];
    }

    /**
     * Perform additional business logic validation.
     */
    protected function performBusinessValidation(string $syncType, string $operation, array $data): void
    {
        match ($syncType) {
            'order' => $this->validateOrderBusiness($operation, $data),
            'inventory' => $this->validateInventoryBusiness($operation, $data),
            'payment' => $this->validatePaymentBusiness($operation, $data),
            'product' => $this->validateProductBusiness($operation, $data),
            default => null,
        };
    }

    /**
     * Validate order business logic.
     */
    protected function validateOrderBusiness(string $operation, array $data): void
    {
        $storeId = $this->resolveStoreId($data, true);

        if ($operation === 'create') {
            // Validate order totals
            if (isset($data['items']) && is_array($data['items'])) {
                $calculatedSubtotal = 0;
                foreach ($data['items'] as $item) {
                    $calculatedSubtotal += $item['total_price'];
                    
                    // Validate item total price
                    $expectedTotal = $item['quantity'] * $item['unit_price'];
                    if (abs($item['total_price'] - $expectedTotal) > 0.01) {
                        throw new \InvalidArgumentException(
                            "Item total price mismatch. Expected: {$expectedTotal}, Got: {$item['total_price']}"
                        );
                    }
                }

                // Validate subtotal
                if (isset($data['subtotal']) && abs($data['subtotal'] - $calculatedSubtotal) > 0.01) {
                    throw new \InvalidArgumentException(
                        "Order subtotal mismatch. Expected: {$calculatedSubtotal}, Got: {$data['subtotal']}"
                    );
                }
            }

            // Validate member exists if provided
            if ($storeId && isset($data['member_id'])) {
                $member = Member::where('store_id', $storeId)
                    ->find($data['member_id']);
                if (!$member) {
                    throw new \InvalidArgumentException("Member not found: {$data['member_id']}");
                }
            }

            // Validate user exists if provided
            if ($storeId && isset($data['user_id'])) {
                $user = User::where('store_id', $storeId)
                    ->find($data['user_id']);
                if (!$user) {
                    throw new \InvalidArgumentException("User not found: {$data['user_id']}");
                }
            }
        }
    }

    /**
     * Validate inventory business logic.
     */
    protected function validateInventoryBusiness(string $operation, array $data): void
    {
        if ($operation === 'create') {
            $storeId = $this->resolveStoreId($data, true);

            // Validate product exists and tracks inventory
            $product = Product::query()
                ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                ->find($data['product_id']);
            
            if (!$product) {
                throw new \InvalidArgumentException("Product not found: {$data['product_id']}");
            }

            // Check if movement type requires inventory tracking
            $trackingRequiredTypes = ['sale', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out'];
            if (in_array($data['type'], $trackingRequiredTypes) && !$product->track_inventory) {
                throw new \InvalidArgumentException(
                    "Product {$product->name} does not track inventory but movement type {$data['type']} requires it"
                );
            }

            // Validate stock availability for outbound movements
            $outboundTypes = ['sale', 'adjustment_out', 'transfer_out', 'waste'];
            if (in_array($data['type'], $outboundTypes)) {
                if ($product->stock_quantity < $data['quantity']) {
                    throw new \InvalidArgumentException(
                        "Insufficient stock. Available: {$product->stock_quantity}, Required: {$data['quantity']}"
                    );
                }
            }

            // Validate cost information for purchase movements
            if ($data['type'] === 'purchase' && !isset($data['unit_cost'])) {
                throw new \InvalidArgumentException("Unit cost is required for purchase movements");
            }
        }
    }

    /**
     * Validate payment business logic.
     */
    protected function validatePaymentBusiness(string $operation, array $data): void
    {
        if ($operation === 'create') {
            $storeId = $this->resolveStoreId($data, true);

            // Validate order exists and belongs to store
            $order = Order::query()
                ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                ->find($data['order_id']);
            
            if (!$order) {
                throw new \InvalidArgumentException("Order not found: {$data['order_id']}");
            }

            // Validate payment amount doesn't exceed remaining balance
            $remainingBalance = $order->getRemainingBalance();
            if ($data['amount'] > $remainingBalance + 0.01) { // Allow small rounding differences
                throw new \InvalidArgumentException(
                    "Payment amount ({$data['amount']}) exceeds remaining balance ({$remainingBalance})"
                );
            }

            // Validate order is not cancelled
            if ($order->status === 'cancelled') {
                throw new \InvalidArgumentException("Cannot add payment to cancelled order");
            }
        }
    }

    /**
     * Validate product business logic.
     */
    protected function validateProductBusiness(string $operation, array $data): void
    {
        if ($operation === 'create') {
            $storeId = $this->resolveStoreId($data, true);
            // Validate SKU uniqueness if provided
            if (isset($data['sku']) && !empty($data['sku'])) {
                $existingProduct = Product::query()
                    ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                    ->where('sku', $data['sku'])
                    ->first();
                
                if ($existingProduct) {
                    throw new \InvalidArgumentException("SKU already exists: {$data['sku']}");
                }
            }

            // Validate price is greater than cost price
            if (isset($data['cost_price']) && $data['cost_price'] > $data['price']) {
                throw new \InvalidArgumentException(
                    "Cost price ({$data['cost_price']}) cannot be greater than selling price ({$data['price']})"
                );
            }
        }
    }

    /**
     * Validate data integrity across related entities.
     */
    public function validateDataIntegrity(array $syncBatch): array
    {
        $issues = [];

        // Group by entity type
        $byType = collect($syncBatch)->groupBy('sync_type');

        // Validate order-payment relationships
        if ($byType->has('order') && $byType->has('payment')) {
            $issues = array_merge($issues, $this->validateOrderPaymentIntegrity(
                $byType->get('order', []),
                $byType->get('payment', [])
            ));
        }

        // Validate order-inventory relationships
        if ($byType->has('order') && $byType->has('inventory')) {
            $issues = array_merge($issues, $this->validateOrderInventoryIntegrity(
                $byType->get('order', []),
                $byType->get('inventory', [])
            ));
        }

        return $issues;
    }

    /**
     * Validate order-payment integrity.
     */
    protected function validateOrderPaymentIntegrity(array $orders, array $payments): array
    {
        $issues = [];
        
        foreach ($payments as $payment) {
            $paymentData = $payment['data'];
            $relatedOrder = collect($orders)->first(function ($order) use ($paymentData) {
                return $order['data']['order_number'] === $paymentData['order_number'] ?? 
                       $order['entity_id'] === $paymentData['order_id'];
            });

            if (!$relatedOrder) {
                $issues[] = [
                    'type' => 'missing_order',
                    'payment_id' => $payment['idempotency_key'],
                    'order_id' => $paymentData['order_id'] ?? 'unknown',
                    'message' => 'Payment references order not found in sync batch',
                ];
                continue;
            }

            // Validate payment amount doesn't exceed order total
            $orderTotal = $relatedOrder['data']['total_amount'];
            if ($paymentData['amount'] > $orderTotal) {
                $issues[] = [
                    'type' => 'payment_exceeds_total',
                    'payment_id' => $payment['idempotency_key'],
                    'order_id' => $relatedOrder['idempotency_key'],
                    'payment_amount' => $paymentData['amount'],
                    'order_total' => $orderTotal,
                    'message' => 'Payment amount exceeds order total',
                ];
            }
        }

        return $issues;
    }

    /**
     * Validate order-inventory integrity.
     */
    protected function validateOrderInventoryIntegrity(array $orders, array $inventoryMovements): array
    {
        $issues = [];

        foreach ($orders as $order) {
            $orderData = $order['data'];
            
            if (!isset($orderData['items']) || !is_array($orderData['items'])) {
                continue;
            }

            foreach ($orderData['items'] as $item) {
                // Find corresponding inventory movement
                $movement = collect($inventoryMovements)->first(function ($inv) use ($item, $order) {
                    $invData = $inv['data'];
                    return $invData['product_id'] === $item['product_id'] &&
                           $invData['type'] === 'sale' &&
                           $invData['quantity'] === $item['quantity'] &&
                           ($invData['reference_id'] ?? null) === ($order['entity_id'] ?? null);
                });

                if (!$movement) {
                    $issues[] = [
                        'type' => 'missing_inventory_movement',
                        'order_id' => $order['idempotency_key'],
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'message' => 'Order item missing corresponding inventory movement',
                    ];
                }
            }
        }

        return $issues;
    }
}
