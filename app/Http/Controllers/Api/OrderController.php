<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\AddOrderItemRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderCollection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Member;
use App\Models\Table;
use App\Services\OrderCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $validator = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $start = Carbon::parse($request->input('start_date'));
                $end = Carbon::parse($request->input('end_date'));

                if ($end->lt($start)) {
                    $validator->errors()->add('end_date', 'The end_date must be after or equal to the start_date.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_ORDER_FILTERS',
                    'message' => 'One or more order filters are invalid.',
                    'details' => $validator->errors(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                ],
            ], 422);
        }

        $filters = $validator->validated();

        // Build query with eager loading - ✅ WAJIB: Load items dan product untuk setiap item
        $query = Order::with([
            'items',           // ✅ WAJIB: Load order items
            'items.product',   // ✅ WAJIB: Load product details untuk setiap item
            'member',
            'table',
            'user:id,name',
            'payments'
        ]);

        // Apply filters
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('operation_mode')) {
            $query->where('operation_mode', $request->input('operation_mode'));
        }

        $dateWindow = [
            'date' => null,
            'start' => null,
            'end' => null,
        ];

        $exactDate = $filters['date'] ?? null;

        if ($exactDate) {
            $startOfDay = Carbon::parse($exactDate)->startOfDay();
            $endOfDay = Carbon::parse($exactDate)->endOfDay();
            $query->whereBetween('created_at', [$startOfDay, $endOfDay]);

            $dateWindow['date'] = $startOfDay->toDateString();
            $dateWindow['start'] = $startOfDay->toDateString();
            $dateWindow['end'] = $endOfDay->toDateString();
        } else {
            $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
            $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('created_at', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('created_at', '<=', $endDate);
            }

            if ($startDate || $endDate) {
                $dateWindow['start'] = $startDate?->toDateString();
                $dateWindow['end'] = $endDate?->toDateString();
            }
        }

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->input('member_id'));
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->input('table_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('member', function ($memberQuery) use ($search) {
                        $memberQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['created_at', 'order_number', 'total_amount', 'status'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min((int)($filters['per_page'] ?? $request->input('per_page', 10)), 100);
        $perPage = $perPage > 0 ? $perPage : 10;
        $orders = $query->paginate($perPage);

        // Log untuk debugging
        $firstOrder = $orders->first();
        Log::info('📋 Fetching orders', [
            'status_filter' => $request->input('status'),
            'total_orders' => $orders->total(),
            'items_loaded' => $firstOrder ? $firstOrder->items->count() : 0,
            'first_order_total' => $firstOrder ? $firstOrder->total_amount : null,
            'first_order_id' => $firstOrder ? $firstOrder->id : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'has_more' => $orders->hasMorePages(),
                'date_window' => $dateWindow,
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Get user from request (should be set by middleware)
            $user = $request->user() ?? auth()->user();
            
            if (!$user) {
                DB::rollBack();
                Log::warning('Order creation attempted without authentication', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'has_token' => $request->bearerToken() !== null,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'User not authenticated. Please provide a valid authentication token.',
                    ],
                ], 401);
            }
            
            $store = $user->store();
            
            if (!$store) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'STORE_NOT_FOUND',
                        'message' => 'User does not have an associated store',
                    ],
                ], 404);
            }

            // Validate tenant context (required for tenant-centric architecture)
            $tenantId = $store->tenant_id ?? $user->currentTenantId();
            if (!$tenantId) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TENANT_CONTEXT_MISSING',
                        'message' => 'No tenant context found for this store. Please contact support.',
                    ],
                ], 400);
            }
            
            // Resolve customer information
            $customerService = app(\App\Services\CustomerResolutionService::class);
            $customerData = $customerService->resolveCustomer($request->all(), $store);
            
            $order = Order::create([
                'tenant_id' => $tenantId,  // Explicit tenant_id for tenant-centric architecture
                'store_id' => $store->id,
                'user_id' => $user->id,  // Staff who created the order
                'member_id' => $customerData['customer_id'],
                'customer_name' => $customerData['customer_name'],
                'customer_type' => $customerData['customer_type'],
                'table_id' => $request->input('table_id'),
                'operation_mode' => $request->input('operation_mode', 'dine_in'),
                'payment_mode' => $request->input('payment_mode', 'direct'),
                'status' => $request->input('status', 'draft'),
                'service_charge' => $request->input('service_charge', 0),
                'discount_amount' => $request->input('discount_amount', 0),
                // ✅ Don't set tax_amount here - let updateOrderTotals calculate it from store settings
                'notes' => $request->input('notes'),
            ]);

            // Add items if provided
            if ($request->has('items')) {
                $calculationService = app(OrderCalculationService::class);
                foreach ($request->input('items') as $itemData) {
                    $this->addItemToOrder($order, $itemData);
                }
                $calculationService->updateOrderTotals($order);
                
                // Deduct inventory if flag is true
                if ($request->input('deduct_inventory', false)) {
                    $this->deductInventoryForOrder($order);
                    
                    Log::info('Inventory deducted for new order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'items_count' => $order->items->count(),
                        'user_id' => $user->id
                    ]);
                }
            }

            // Assign table if provided
            if ($order->table_id) {
                $table = Table::find($order->table_id);
                if ($table && $table->isAvailable()) {
                    $table->occupy($order);
                }
            }

            // Track subscription usage (soft cap, no blocking)
            if ($store && $store->tenant_id) {
                try {
                    $planLimitService = app(\App\Services\PlanLimitService::class);
                    $planLimitService->trackUsage($store, 'transactions', 1);
                    
                    Log::info('Subscription usage tracked for order', [
                        'order_id' => $order->id,
                        'tenant_id' => $store->tenant_id,
                        'feature_type' => 'transactions',
                    ]);
                } catch (\Exception $e) {
                    // Don't fail order creation if usage tracking fails
                    Log::warning('Failed to track subscription usage', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            $order->load(['items.product', 'member', 'table', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'Order created successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_CREATION_FAILED',
                    'message' => 'Failed to create order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(string $id): JsonResponse
    {
        $order = Order::with(['items.product', 'member', 'table', 'user:id,name', 'payments'])->findOrFail($id);
        $this->authorize('view', $order);

        $order->load(['items.product.options', 'member', 'table', 'user:id,name', 'payments', 'refunds']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Update the specified order.
     */
    public function update(UpdateOrderRequest $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);

        if (!$order->canBeModified()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_MODIFIABLE',
                    'message' => 'This order cannot be modified in its current status.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            Log::info('📝 Updating order', [
                'order_id' => $id,
                'has_items' => $request->has('items'),
                'update_inventory' => $request->input('update_inventory', false),
                'restore_inventory' => $request->input('restore_inventory', false),
                'cancel_payment' => $request->input('cancel_payment', false),
            ]);

            // ✅ FITUR BARU: Handle inventory update saat edit items (granular approach)
            // Hanya jalan jika parameter 'update_inventory' = true
            if ($request->has('update_inventory') && $request->input('update_inventory', false) && $request->has('items')) {
                $this->updateInventoryForOrderEdit($order, $request->input('items'));
                
                // Update items setelah inventory adjustment
                $this->updateOrderItems($order, $request->input('items'));
                
                // Recalculate totals
                $order->calculateTotals();
            } elseif ($request->has('items')) {
                // Update items tanpa inventory adjustment (backward compatible)
                $this->updateOrderItems($order, $request->input('items'));
                $order->calculateTotals();
            }

            // ✅ FITUR BARU: Handle restore inventory saat cancel order
            // Hanya jalan jika parameter 'restore_inventory' = true
            if ($request->has('restore_inventory') && $request->input('restore_inventory', false)) {
                $items = $order->items()->with('product')->get();
                $this->restoreInventoryForItems($items);
                
                Log::info('✅ Inventory restored via update endpoint', [
                    'order_id' => $order->id,
                    'items_count' => $items->count()
                ]);
            }

            // ✅ FITUR BARU: Handle cancel payment saat order dibatalkan
            // Hanya jalan jika parameter 'cancel_payment' = true
            if ($request->has('cancel_payment') && $request->input('cancel_payment', false)) {
                $this->cancelPendingPayment($order);
            }

            // Update other order fields
            $updateData = $request->only(['operation_mode', 'table_id', 'notes', 'status', 'member_id', 'service_charge', 'discount_amount', 'tax_amount']); // ✅ Add tax_amount
            $order->update(array_filter($updateData, function($value) {
                return $value !== null;
            }));

            // Update table assignment if changed
            if ($request->has('table_id') && $order->table_id !== $request->input('table_id')) {
                // Free old table
                if ($order->table) {
                    $order->table->makeAvailable();
                }

                // Assign new table
                if ($request->input('table_id')) {
                    $table = Table::find($request->input('table_id'));
                    if ($table && $table->isAvailable()) {
                        $table->occupy($order);
                    }
                }
            }

            // Recalculate totals if items were not updated
            if (!$request->has('items')) {
                $order->calculateTotals();
            }

            DB::commit();

            $order->load(['items.product', 'member', 'table', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'Order updated successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Order update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_UPDATE_FAILED',
                    'message' => 'Failed to update order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Remove the specified order.
     */
    public function destroy(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->authorize('delete', $order);

        if (!$order->canBeModified()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_DELETABLE',
                    'message' => 'This order cannot be deleted in its current status.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Free table if assigned
            if ($order->table) {
                $order->table->makeAvailable();
            }

            // Restore inventory for all items
            foreach ($order->items as $item) {
                if ($item->product && $item->product->track_inventory) {
                    $item->product->increaseStock($item->quantity);
                }
            }

            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Order deletion failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_DELETION_FAILED',
                    'message' => 'Failed to delete order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Add item to order.
     */
    public function addItem(AddOrderItemRequest $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);

        if (!$order->canBeModified()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_MODIFIABLE',
                    'message' => 'Cannot add items to this order in its current status.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $itemData = $request->validated();
            $orderItem = $this->addItemToOrder($order, $itemData);

            $order->calculateTotals();

            DB::commit();

            $orderItem->load('product');

            return response()->json([
                'success' => true,
                'data' => $orderItem,
                'message' => 'Item added to order successfully',
                'meta' => [
                    'order_total' => $order->fresh()->total_amount,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Add item to order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADD_ITEM_FAILED',
                    'message' => 'Failed to add item to order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Update order item.
     */
    public function updateItem(Request $request, string $orderId, string $itemId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $item = $order->items()->findOrFail($itemId);
        $this->authorize('update', $order);

        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ITEM_NOT_FOUND',
                    'message' => 'Item not found in this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }

        if (!$order->canBeModified()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_MODIFIABLE',
                    'message' => 'Cannot modify items in this order status.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $oldQuantity = $item->quantity;
            $newQuantity = $request->input('quantity');

            $item->update([
                'quantity' => $newQuantity,
                'notes' => $request->input('notes'),
            ]);

            // Update inventory
            if ($item->product && $item->product->track_inventory) {
                $quantityDiff = $newQuantity - $oldQuantity;
                if ($quantityDiff > 0) {
                    $item->product->reduceStock($quantityDiff);
                } elseif ($quantityDiff < 0) {
                    $item->product->increaseStock(abs($quantityDiff));
                }
            }

            $order->calculateTotals();

            DB::commit();

            $item->load('product');

            return response()->json([
                'success' => true,
                'data' => $item,
                'message' => 'Order item updated successfully',
                'meta' => [
                    'order_total' => $order->fresh()->total_amount,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Update order item failed', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_ITEM_FAILED',
                    'message' => 'Failed to update order item. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Remove item from order.
     */
    public function removeItem(string $orderId, string $itemId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $item = $order->items()->findOrFail($itemId);
        $this->authorize('update', $order);

        if ($item->order_id !== $order->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ITEM_NOT_FOUND',
                    'message' => 'Item not found in this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }

        if (!$order->canBeModified()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_MODIFIABLE',
                    'message' => 'Cannot remove items from this order status.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Restore inventory
            if ($item->product && $item->product->track_inventory) {
                $item->product->increaseStock($item->quantity);
            }

            $item->delete();
            $order->calculateTotals();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item removed from order successfully',
                'meta' => [
                    'order_total' => $order->fresh()->total_amount,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Remove order item failed', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REMOVE_ITEM_FAILED',
                    'message' => 'Failed to remove item from order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Complete an order.
     */
    public function complete(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);

        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_ALREADY_COMPLETED',
                    'message' => 'This order is already completed.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        if ($order->items()->count() === 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_EMPTY',
                    'message' => 'Cannot complete an order with no items.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $order->complete();

            DB::commit();

            $order->load(['items.product', 'member', 'table', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'Order completed successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Order completion failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_COMPLETION_FAILED',
                    'message' => 'Failed to complete order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Get order summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $date = $request->input('date', now()->toDateString());

        $baseQuery = Order::whereDate('created_at', $date);

        $summary = [
            'total_orders' => (clone $baseQuery)->count(),
            'completed_orders' => (clone $baseQuery)->completed()->count(),
            'open_orders' => (clone $baseQuery)->byStatus('open')->count(),
            'draft_orders' => (clone $baseQuery)->byStatus('draft')->count(),
            'total_revenue' => (clone $baseQuery)->completed()->sum('total_amount'),
            'average_order_value' => (clone $baseQuery)->completed()->avg('total_amount') ?? 0,
            'total_items_sold' => (clone $baseQuery)->completed()
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->sum('order_items.quantity'),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'meta' => [
                'date' => $date,
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Helper method to add item to order.
     */
    private function addItemToOrder(Order $order, array $itemData): OrderItem
    {
        $product = Product::findOrFail($itemData['product_id']);

        // Validate product options if provided
        if (!empty($itemData['product_options'])) {
            $errors = $product->validateOptions($itemData['product_options']);
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Invalid product options: ' . implode(', ', $errors));
            }
        }

        // Calculate price with options
        $priceCalculation = $product->calculatePriceWithOptions($itemData['product_options'] ?? []);

        $orderItem = $order->items()->create([
            'store_id' => $order->store_id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => $itemData['quantity'],
            'unit_price' => $priceCalculation['total_price'],
            'product_options' => $priceCalculation['selected_options'],
            'notes' => $itemData['notes'] ?? null,
        ]);

        // NOTE: Inventory is now managed explicitly via deduct_inventory flag
        // The automatic inventory deduction has been removed to give explicit control

        return $orderItem;
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);

        $request->validate([
            'restore_inventory' => 'nullable|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_ALREADY_CANCELLED',
                    'message' => 'This order is already cancelled.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        if ($order->status === 'completed') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_ALREADY_COMPLETED',
                    'message' => 'Cannot cancel a completed order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        // Check if order has payments
        if ($order->payments()->where('status', 'completed')->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_HAS_PAYMENTS',
                    'message' => 'Cannot cancel order with completed payments. Please process refunds first.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Restore inventory if requested
            if ($request->input('restore_inventory', false)) {
                $items = $order->items()->with('product')->get();
                $this->restoreInventoryForItems($items);
                
                $userId = auth()->check() ? auth()->id() : null;
                Log::info('Inventory restored for cancelled order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'items_count' => $items->count(),
                    'user_id' => $userId
                ]);
            }

            // Free table if assigned
            if ($order->table) {
                $order->table->makeAvailable();
            }

            // Cancel pending payments (use new method for consistency)
            $this->cancelPendingPayment($order);

            // Update order status with optional reason
            $order->update([
                'status' => 'cancelled',
                'notes' => $request->input('reason') ? 
                    ($order->notes ? $order->notes . "\n\nCancellation reason: " . $request->input('reason') : 
                    "Cancellation reason: " . $request->input('reason')) : 
                    $order->notes
            ]);

            DB::commit();

            $order->load(['items.product', 'member', 'table', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'Order cancelled successfully',
                'meta' => [
                    'inventory_restored' => $request->input('restore_inventory', false),
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $userId = auth()->check() ? auth()->id() : null;
            Log::error('Order cancellation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_CANCELLATION_FAILED',
                    'message' => 'Failed to cancel order. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }

    /**
     * Deduct inventory for order items.
     */
    private function deductInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;
            
            // Only deduct if product tracks inventory
            if ($product && $product->track_inventory) {
                $newStock = $product->stock - $item->quantity;
                
                // Prevent negative stock
                if ($newStock < 0) {
                    throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$product->stock}, Requested: {$item->quantity}");
                }
                
                $product->decrement('stock', $item->quantity);
                
                Log::info('Inventory deducted', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item->quantity,
                    'old_stock' => $product->stock + $item->quantity,
                    'new_stock' => $product->stock,
                    'order_id' => $order->id
                ]);
            }
        }
    }

    /**
     * Restore inventory for items.
     */
    private function restoreInventoryForItems($items): void
    {
        foreach ($items as $item) {
            $product = $item->product;
            
            // Only restore if product tracks inventory
            if ($product && $product->track_inventory) {
                $product->increment('stock', $item->quantity);
                
                Log::info('Inventory restored', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item->quantity,
                    'new_stock' => $product->stock,
                    'order_id' => $item->order_id ?? null
                ]);
            }
        }
    }

    /**
     * ✅ NEW METHOD: Update inventory saat edit open bill
     * Method ini TIDAK akan berjalan untuk request existing
     * Granular approach: compare old vs new items per product_id
     */
    private function updateInventoryForOrderEdit(Order $order, array $newItems): void
    {
        try {
            Log::info('📦 [NEW FEATURE] Updating inventory for order edit', [
                'order_id' => $order->id,
            ]);

            // Get old items keyed by product_id
            $oldItems = $order->items()->with('product')->get()->keyBy('product_id');
            $newItemsCollection = collect($newItems)->keyBy(function ($item) {
                return $item['product_id'];
            });

            Log::info('Inventory update comparison', [
                'old_items_count' => $oldItems->count(),
                'new_items_count' => $newItemsCollection->count(),
            ]);

            // 1. Check untuk items yang dihapus (restore stock)
            foreach ($oldItems as $productId => $oldItem) {
                if (!$newItemsCollection->has($productId)) {
                    $product = $oldItem->product;
                    if ($product && $product->track_inventory) {
                        $oldStock = $product->stock;
                        $product->increment('stock', $oldItem->quantity);
                        
                        Log::info("✅ Restored stock for deleted item", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $oldItem->quantity,
                            'old_stock' => $oldStock,
                            'new_stock' => $product->stock
                        ]);
                    }
                }
            }

            // 2. Check untuk items yang berubah quantity atau item baru
            foreach ($newItemsCollection as $productId => $newItem) {
                $product = Product::find($productId);
                if (!$product || !$product->track_inventory) {
                    Log::info("ℹ️ Skipping product (not found or tracking disabled)", [
                        'product_id' => $productId
                    ]);
                    continue;
                }

                if ($oldItems->has($productId)) {
                    // Item existing, check perubahan quantity
                    $oldQuantity = $oldItems[$productId]->quantity;
                    $newQuantity = (int) $newItem['quantity'];
                    $diff = $newQuantity - $oldQuantity;

                    if ($diff > 0) {
                        // Quantity bertambah, kurangi stock
                        $oldStock = $product->stock;
                        $newStock = $product->stock - $diff;
                        
                        if ($newStock < 0) {
                            throw new \Exception("Stock tidak cukup untuk {$product->name}. Stock tersedia: {$product->stock}, dibutuhkan: {$diff}");
                        }
                        
                        $product->update(['stock' => $newStock]);
                        
                        Log::info("✅ Deducted stock for increased quantity", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_diff' => -$diff,
                            'old_stock' => $oldStock,
                            'new_stock' => $product->stock
                        ]);
                    } elseif ($diff < 0) {
                        // Quantity berkurang, kembalikan stock
                        $oldStock = $product->stock;
                        $product->increment('stock', abs($diff));
                        
                        Log::info("✅ Restored stock for decreased quantity", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_diff' => abs($diff),
                            'old_stock' => $oldStock,
                            'new_stock' => $product->stock
                        ]);
                    } else {
                        Log::info("ℹ️ No quantity change", [
                            'product_id' => $product->id,
                            'product_name' => $product->name
                        ]);
                    }
                } else {
                    // Item baru ditambahkan
                    $quantity = (int) $newItem['quantity'];
                    $oldStock = $product->stock;
                    $newStock = $product->stock - $quantity;
                    
                    if ($newStock < 0) {
                        throw new \Exception("Stock tidak cukup untuk {$product->name}. Stock tersedia: {$product->stock}, dibutuhkan: {$quantity}");
                    }
                    
                    $product->update(['stock' => $newStock]);
                    
                    Log::info("✅ Deducted stock for new item", [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'old_stock' => $oldStock,
                        'new_stock' => $product->stock
                    ]);
                }
            }

            Log::info('✅ Inventory update completed successfully', [
                'order_id' => $order->id
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to update inventory', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ NEW METHOD: Cancel pending payment saat order dibatalkan
     * Update payment_method menjadi 'cancelled' dan status menjadi 'cancelled'
     */
    private function cancelPendingPayment(Order $order): void
    {
        try {
            Log::info('💳 [NEW FEATURE] Canceling pending payment for order', [
                'order_id' => $order->id
            ]);

            $payment = Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if ($payment) {
                $payment->update([
                    'payment_method' => 'cancelled',
                    'status' => 'cancelled',
                ]);
                
                Log::info('✅ Pending payment cancelled successfully', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id
                ]);
            } else {
                Log::info('ℹ️ No pending payment found for this order', [
                    'order_id' => $order->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to cancel pending payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ NEW METHOD: Update order items
     * Update order items jika request punya items
     */
    private function updateOrderItems(Order $order, array $items): void
    {
        try {
            Log::info('📝 Updating order items', [
                'order_id' => $order->id,
                'items_count' => count($items)
            ]);

            // Delete existing items
            $order->items()->delete();

            // Insert new items
            foreach ($items as $item) {
                $order->items()->create([
                    'store_id' => $order->store_id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'] ?? ($item['unit_price'] * $item['quantity']),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // ✅ CRITICAL: Recalculate totals setelah items di-update
            $order->calculateTotals();

            Log::info('✅ Order items updated successfully', [
                'order_id' => $order->id,
                'new_total_amount' => $order->fresh()->total_amount
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Failed to update order items', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
