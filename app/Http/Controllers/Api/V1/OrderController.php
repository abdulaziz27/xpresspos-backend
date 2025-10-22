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
use App\Models\Product;
use App\Models\Member;
use App\Models\Table;
use App\Services\OrderCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with(['items.product', 'member', 'table', 'user:id,name']);

        // Apply filters
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
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
        $perPage = min($request->input('per_page', 15), 100);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
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

            $user = auth()->user() ?? request()->user();
            $order = Order::create([
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'member_id' => $request->input('member_id'),
                'table_id' => $request->input('table_id'),
                'status' => $request->input('status', 'draft'),
                'service_charge' => $request->input('service_charge', 0),
                'discount_amount' => $request->input('discount_amount', 0),
                'notes' => $request->input('notes'),
            ]);

            // Add items if provided
            if ($request->has('items')) {
                $calculationService = app(OrderCalculationService::class);
                foreach ($request->input('items') as $itemData) {
                    $this->addItemToOrder($order, $itemData);
                }
                $calculationService->updateOrderTotals($order);
            }

            // Assign table if provided
            if ($order->table_id) {
                $table = Table::find($order->table_id);
                if ($table && $table->isAvailable()) {
                    $table->occupy($order);
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
            Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
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

            $order->update($request->validated());

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

            $order->calculateTotals();

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
            Log::error('Order update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
            Log::error('Order deletion failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
            Log::error('Add item to order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
            Log::error('Update order item failed', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
            Log::error('Remove order item failed', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
            Log::error('Order completion failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
            'total_items_sold' => (clone $baseQuery)->completed()->sum('total_items'),
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

        // Update inventory
        if ($product->track_inventory) {
            $product->reduceStock($itemData['quantity']);
        }

        return $orderItem;
    }

    /**
     * Cancel an order.
     */
    public function cancel(string $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $this->authorize('update', $order);

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

            // Restore inventory for all items
            foreach ($order->items as $item) {
                if ($item->product && $item->product->track_inventory) {
                    $item->product->increaseStock($item->quantity);
                }
            }

            // Free table if assigned
            if ($order->table) {
                $order->table->makeAvailable();
            }

            // Cancel pending payments
            $order->payments()->where('status', 'pending')->update(['status' => 'cancelled']);

            // Update order status
            $order->update(['status' => 'cancelled']);

            DB::commit();

            $order->load(['items.product', 'member', 'table', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'Order cancelled successfully',
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order cancellation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
    }}
