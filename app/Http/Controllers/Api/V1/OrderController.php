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
use App\Models\StockLevel;
use App\Models\InventoryItem;
use App\Services\OrderCalculationService;
use App\Services\InventoryService;
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

        // Get user and ensure StoreContext is set correctly
        $user = $request->user() ?? auth()->user();
        $storeContext = \App\Services\StoreContext::instance();
        
        // CRITICAL: For owner users, ensure StoreContext is set to their primary store
        // This ensures StoreScope filters correctly
        if ($user) {
            $currentStoreId = $storeContext->current($user);
            $userStores = $user->stores()->pluck('stores.id')->toArray();
            
            // If no store context or current store not in user's stores, set to primary store
            if (!$currentStoreId || !in_array($currentStoreId, $userStores)) {
                $primaryStore = $user->primaryStore();
                if ($primaryStore) {
                    $storeContext->setForUser($user, $primaryStore->id);
                    $currentStoreId = $primaryStore->id;
                    Log::info('StoreContext set to primary store for order listing', [
                        'user_id' => $user->id,
                        'store_id' => $primaryStore->id,
                    ]);
                } elseif (!empty($userStores)) {
                    // Fallback to first store
                    $firstStoreId = $userStores[0];
                    $storeContext->setForUser($user, $firstStoreId);
                    $currentStoreId = $firstStoreId;
                    Log::info('StoreContext set to first store for order listing', [
                        'user_id' => $user->id,
                        'store_id' => $firstStoreId,
                    ]);
                }
            }
            
            // For owner users, query all stores they own, not just one store
            // This ensures orders from all stores are visible
            $hasOwnerRole = $user->hasRole('owner');
            $hasOwnerAssignment = $user->storeAssignments()
                ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
                ->exists();
            $isOwner = $hasOwnerRole || $hasOwnerAssignment;
            
            if ($isOwner && !empty($userStores)) {
                // Owner can see orders from all their stores
                // Use withoutGlobalScopes to bypass StoreScope, then filter manually
                $query = Order::withoutGlobalScopes()
                    ->whereIn('store_id', $userStores)
                    ->with([
                        'items',
                        'items.product',
                        'member',
                        'table',
                        'user:id,name',
                        'payments'
                    ]);
                
                // Also filter by tenant_id if available to ensure data isolation
                $tenantId = $user->currentTenantId();
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                
                Log::info('Owner querying orders from multiple stores', [
                    'user_id' => $user->id,
                    'has_owner_role' => $hasOwnerRole,
                    'has_owner_assignment' => $hasOwnerAssignment,
                    'store_ids' => $userStores,
                    'tenant_id' => $tenantId,
                ]);
            } else {
                // For non-owner users (cashier, etc.), use normal scoped query
                // StoreScope will automatically filter by current store
                $query = Order::with([
                    'items',
                    'items.product',
                    'member',
                    'table',
                    'user:id,name',
                    'payments'
                ]);
                
                Log::info('Non-owner querying orders (using StoreScope)', [
                    'user_id' => $user->id,
                    'current_store_id' => $currentStoreId,
                ]);
            }
        } else {
            // Fallback if no user (shouldn't happen due to auth middleware)
            $query = Order::with([
                'items',
                'items.product',
                'member',
                'table',
                'user:id,name',
                'payments'
            ]);
        }

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
        // Authorization check
        $this->authorize('create', Order::class);

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

            // CRITICAL: Set StoreContext to ensure global scope works correctly
            // This ensures the order can be queried later without being filtered out
            $storeContext = \App\Services\StoreContext::instance();
            if (!$storeContext->current($user) || $storeContext->current($user) !== $store->id) {
                $storeContext->setForUser($user, $store->id);
                Log::info('StoreContext set for order creation', [
                    'user_id' => $user->id,
                    'store_id' => $store->id,
                ]);
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
            
            // Create order with retry mechanism to handle duplicate order_number race conditions
            $maxRetries = 5;
            $order = null;
            $lastException = null;
            
            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                try {
                    // Always create a fresh Order instance to ensure clean state
                    // This ensures order_number is regenerated on each retry
                    $order = new Order([
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
                        'notes' => $request->input('notes'),
                        // Explicitly set order_number to null to force regeneration via model event
                        'order_number' => null,
                    ]);
                    
                    $order->save();
                    break; // Success, exit retry loop
                } catch (\Illuminate\Database\QueryException $e) {
                    $lastException = $e;
                    $errorMessage = $e->getMessage();
                    
                    // Check if it's a duplicate order_number error (more robust check)
                    // Error code 23000 = Integrity constraint violation
                    // Check for various forms of order_number unique constraint errors
                    $isDuplicateOrderNumber = $e->getCode() == 23000 && (
                        str_contains($errorMessage, 'order_number') || 
                        str_contains($errorMessage, 'orders_order_number_unique') ||
                        (str_contains($errorMessage, 'Duplicate entry') && str_contains($errorMessage, 'ORD'))
                    );
                    
                    if ($isDuplicateOrderNumber) {
                        if ($attempt < $maxRetries - 1) {
                            // Log the retry attempt
                            Log::warning('Order creation retry due to duplicate order_number', [
                                'attempt' => $attempt + 1,
                                'max_retries' => $maxRetries,
                                'error' => $errorMessage,
                            ]);
                            
                            // Wait a bit before retry (order_number will be regenerated by model event)
                            // Use exponential backoff with jitter
                            $delay = (100000 * pow(2, $attempt)) + rand(0, 50000); // 100ms, 200ms, 400ms, etc.
                            usleep($delay);
                            continue;
                        }
                    }
                    // Re-throw if not a duplicate error or max retries reached
                    throw $e;
                }
            }
            
            if (!$order) {
                DB::rollBack();
                Log::error('Failed to create order after retries', [
                    'attempts' => $maxRetries,
                    'exception' => $lastException?->getMessage(),
                    'store_id' => $store->id,
                    'tenant_id' => $tenantId,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ORDER_CREATION_FAILED',
                        'message' => 'Failed to create order. Please try again.',
                        'details' => $lastException?->getMessage(),
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 500);
            }

            // Add items if provided
            if ($request->has('items') && !empty($request->input('items'))) {
                $calculationService = app(OrderCalculationService::class);
                $itemsAdded = 0;
                $itemsFailed = 0;
                
                foreach ($request->input('items') as $itemData) {
                    try {
                        $this->addItemToOrder($order, $itemData);
                        $itemsAdded++;
                    } catch (\Exception $e) {
                        $itemsFailed++;
                        Log::error('Failed to add item to order', [
                            'order_id' => $order->id,
                            'item_data' => $itemData,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Re-throw to trigger rollback
                        throw new \Exception("Failed to add item to order: {$e->getMessage()}", 0, $e);
                    }
                }
                
                if ($itemsAdded === 0 && $itemsFailed > 0) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'ITEMS_ADDITION_FAILED',
                            'message' => 'Failed to add items to order. Please check product IDs and try again.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1'
                        ]
                    ], 422);
                }
                
                // Refresh order to get latest items
                $order->refresh();
                $calculationService->updateOrderTotals($order);
                
                Log::info('Items added to order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'items_added' => $itemsAdded,
                    'items_failed' => $itemsFailed,
                    'total_items' => $order->items->count(),
                    'user_id' => $user->id
                ]);
                
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

            // Refresh order to ensure we have latest data
            $order->refresh();
            $order->load(['items.product', 'member', 'table', 'user:id,name']);

            // Verify order was actually saved
            $savedOrder = Order::find($order->id);
            if (!$savedOrder) {
                Log::error('Order not found after commit', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ORDER_SAVE_FAILED',
                        'message' => 'Order was not saved properly. Please try again.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 500);
            }

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'store_id' => $store->id,
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'items_count' => $order->items->count(),
                'total_amount' => $order->total_amount,
            ]);

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
        $user = request()->user() ?? auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'User not authenticated. Please provide a valid authentication token.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 401);
        }
        
        $storeContext = \App\Services\StoreContext::instance();
        $userStores = $user->stores()->pluck('stores.id')->toArray();
        
        // Check if user is owner
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;
        
        // Find order - for owner, bypass StoreScope; for others, use normal scope
        if ($isOwner && !empty($userStores)) {
            // Owner: query without StoreScope, then verify access
            $order = Order::withoutGlobalScopes()
                ->where('id', $id)
                ->whereIn('store_id', $userStores)
                ->with(['items.product', 'member', 'table', 'user:id,name', 'payments'])
                ->first();
            
            // Also filter by tenant_id for security
            if ($order) {
                $tenantId = $user->currentTenantId();
                if ($tenantId && $order->tenant_id !== $tenantId) {
                    $order = null;
                }
            }
        } else {
            // Non-owner: use normal StoreScope
            // But first, ensure StoreContext is set correctly
            $currentStoreId = $storeContext->current($user);
            if (!$currentStoreId || !in_array($currentStoreId, $userStores)) {
                $primaryStore = $user->primaryStore();
                if ($primaryStore) {
                    $storeContext->setForUser($user, $primaryStore->id);
                    $currentStoreId = $primaryStore->id;
                } elseif (!empty($userStores)) {
                    $storeContext->setForUser($user, $userStores[0]);
                    $currentStoreId = $userStores[0];
                }
            }
            
            $order = Order::with(['items.product', 'member', 'table', 'user:id,name', 'payments'])->find($id);
        }
        
        // If order not found, provide helpful error message
        if (!$order) {
            // Check if order exists at all (for better error message)
            $orderExists = Order::withoutGlobalScopes()
                ->where('id', $id)
                ->first();
            
            if ($orderExists) {
                $orderStoreId = $orderExists->store_id;
                $hasAccess = in_array($orderStoreId, $userStores);
                
                Log::warning('Order found but not accessible', [
                    'order_id' => $id,
                    'user_id' => $user->id,
                    'is_owner' => $isOwner,
                    'order_store_id' => $orderStoreId,
                    'user_stores' => $userStores,
                    'has_access' => $hasAccess,
                ]);
                
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'ORDER_ACCESS_DENIED',
                            'message' => 'Order not found or you do not have access to this order.',
                            'details' => 'The order belongs to a different store.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1'
                        ]
                    ], 403);
                }
                
                // Order exists and user has access, but StoreContext might be wrong
                // Set StoreContext to order's store and retry
                $storeContext->setForUser($user, $orderStoreId);
                $order = Order::with(['items.product', 'member', 'table', 'user:id,name', 'payments'])->find($id);
                
                if (!$order) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'ORDER_ACCESS_DENIED',
                            'message' => 'Order not found or you do not have access to this order.',
                            'details' => 'Unable to access order. Please try again.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1'
                        ]
                    ], 403);
                }
            } else {
                // Order doesn't exist at all
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'ORDER_NOT_FOUND',
                        'message' => 'Order not found.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 404);
            }
        }
        
        // Verify user has access to this order's store
        if (!in_array($order->store_id, $userStores)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_ACCESS_DENIED',
                    'message' => 'Order not found or you do not have access to this order.',
                    'details' => 'The order belongs to a different store.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 403);
        }
        
        // Set StoreContext to order's store to ensure consistency
        if ($storeContext->current($user) !== $order->store_id) {
            $storeContext->setForUser($user, $order->store_id);
            Log::info('StoreContext set to order store for show', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'store_id' => $order->store_id,
            ]);
        }
        
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
        $user = $request->user() ?? auth()->user();
        $order = $this->findOrderForUser($id, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
            $updateData = $request->only(['operation_mode', 'table_id', 'notes', 'status', 'member_id', 'service_charge', 'discount_amount']);
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
        $user = request()->user() ?? auth()->user();
        $order = $this->findOrderForUser($id, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
                if ($item->product) {
                    $this->restoreInventoryForProduct($item->product, $item->quantity, $order->id);
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
        $user = $request->user() ?? auth()->user();
        $order = $this->findOrderForUser($id, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
        $user = $request->user() ?? auth()->user();
        $order = $this->findOrderForUser($orderId, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
                    // Quantity increased, deduct inventory
                    $this->deductInventoryForProduct($item->product, $quantityDiff, $order->id);
                } elseif ($quantityDiff < 0) {
                    // Quantity decreased, restore inventory
                    $this->restoreInventoryForProduct($item->product, abs($quantityDiff), $order->id);
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
        $user = request()->user() ?? auth()->user();
        $order = $this->findOrderForUser($orderId, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
            if ($item->product) {
                $this->restoreInventoryForProduct($item->product, $item->quantity, $order->id);
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
        $user = request()->user() ?? auth()->user();
        $order = $this->findOrderForUser($id, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
     * Helper method to find order with proper store context handling.
     * Handles owner users (bypass StoreScope) and non-owner users (use StoreScope).
     */
    private function findOrderForUser(string $orderId, $user = null): ?Order
    {
        if (!$user) {
            $user = request()->user() ?? auth()->user();
        }
        
        if (!$user) {
            return null;
        }
        
        $storeContext = \App\Services\StoreContext::instance();
        $userStores = $user->stores()->pluck('stores.id')->toArray();
        
        // Check if user is owner
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;
        
        // Find order - for owner, bypass StoreScope; for others, use normal scope
        if ($isOwner && !empty($userStores)) {
            // Owner: query without StoreScope, then verify access
            $order = Order::withoutGlobalScopes()
                ->where('id', $orderId)
                ->whereIn('store_id', $userStores)
                ->first();
            
            // Also filter by tenant_id for security
            if ($order) {
                $tenantId = $user->currentTenantId();
                if ($tenantId && $order->tenant_id !== $tenantId) {
                    $order = null;
                }
            }
        } else {
            // Non-owner: use normal StoreScope
            // But first, ensure StoreContext is set correctly
            $currentStoreId = $storeContext->current($user);
            if (!$currentStoreId || !in_array($currentStoreId, $userStores)) {
                $primaryStore = $user->primaryStore();
                if ($primaryStore) {
                    $storeContext->setForUser($user, $primaryStore->id);
                    $currentStoreId = $primaryStore->id;
                } elseif (!empty($userStores)) {
                    $storeContext->setForUser($user, $userStores[0]);
                    $currentStoreId = $userStores[0];
                }
            }
            
            $order = Order::find($orderId);
        }
        
        // Verify user has access to this order's store
        if ($order && !in_array($order->store_id, $userStores)) {
            return null;
        }
        
        // Set StoreContext to order's store to ensure consistency
        if ($order && $storeContext->current($user) !== $order->store_id) {
            $storeContext->setForUser($user, $order->store_id);
        }
        
        return $order;
    }

    /**
     * Helper method to add item to order.
     */
    private function addItemToOrder(Order $order, array $itemData): OrderItem
    {
        Log::info('Adding item to order', [
            'order_id' => $order->id,
            'product_id' => $itemData['product_id'] ?? null,
            'quantity' => $itemData['quantity'] ?? null,
        ]);

        // Validate product_id is provided
        if (empty($itemData['product_id'])) {
            throw new \InvalidArgumentException('Product ID is required');
        }

        // Find product - use withoutGlobalScopes to ensure we can find products across tenants
        // The product should still be validated against tenant_id in the request validation
        $product = Product::withoutGlobalScopes()->find($itemData['product_id']);
        
        if (!$product) {
            Log::error('Product not found', [
                'product_id' => $itemData['product_id'],
                'order_id' => $order->id,
            ]);
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Product with ID {$itemData['product_id']} not found"
            );
        }

        // Validate product belongs to the same tenant as the order
        if ($product->tenant_id !== $order->tenant_id) {
            Log::error('Product tenant mismatch', [
                'product_id' => $product->id,
                'product_tenant_id' => $product->tenant_id,
                'order_tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
            ]);
            throw new \InvalidArgumentException(
                "Product does not belong to the same tenant as the order"
            );
        }

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

        Log::info('Item added to order successfully', [
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'quantity' => $itemData['quantity'],
            'unit_price' => $priceCalculation['total_price'],
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
        $user = $request->user() ?? auth()->user();
        $order = $this->findOrderForUser($id, $user);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_FOUND',
                    'message' => 'Order not found or you do not have access to this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
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
            if (!$product || !$product->track_inventory) {
                continue;
            }
            
            // Get active recipe for product
            $activeRecipe = $product->getActiveRecipe();
            if (!$activeRecipe) {
                Log::warning('Product has track_inventory=true but no active recipe', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'order_id' => $order->id,
                ]);
                continue;
            }
            
            // Load recipe items with inventory items
            $activeRecipe->load('items.inventoryItem');
            if ($activeRecipe->items->isEmpty()) {
                Log::warning('Product recipe has no items', [
                    'product_id' => $product->id,
                    'recipe_id' => $activeRecipe->id,
                    'order_id' => $order->id,
                ]);
                continue;
            }
            
            // Calculate quantity needed for each inventory item based on recipe
            $storeId = $order->store_id;
            $inventoryService = app(InventoryService::class);
            
            foreach ($activeRecipe->items as $recipeItem) {
                $inventoryItem = $recipeItem->inventoryItem;
                if (!$inventoryItem || !$inventoryItem->track_stock) {
                    continue;
                }
                
                // Calculate quantity needed: (recipe_item.quantity / recipe.yield_quantity) * order_item.quantity
                $yieldQty = $activeRecipe->yield_quantity > 0 ? $activeRecipe->yield_quantity : 1;
                $quantityNeeded = ($recipeItem->quantity / $yieldQty) * $item->quantity;
                
                try {
                    // Deduct stock using InventoryService
                    $inventoryService->processSale(
                        $inventoryItem->id,
                        $quantityNeeded,
                        $order->id
                    );
                    
                    Log::info('Inventory deducted via recipe', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'inventory_item_id' => $inventoryItem->id,
                        'inventory_item_name' => $inventoryItem->name,
                        'quantity_needed' => $quantityNeeded,
                        'order_item_quantity' => $item->quantity,
                        'order_id' => $order->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to deduct inventory for recipe item', [
                        'product_id' => $product->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity_needed' => $quantityNeeded,
                        'error' => $e->getMessage(),
                        'order_id' => $order->id,
                    ]);
                    throw new \Exception("Insufficient stock for product: {$product->name}. {$e->getMessage()}");
                }
            }
        }
    }

    /**
     * Deduct inventory for a product through its recipe.
     * 
     * @param Product $product
     * @param float $quantity Product quantity to deduct
     * @param string $orderId Order ID for reference
     * @throws \Exception If insufficient stock
     */
    private function deductInventoryForProduct(Product $product, float $quantity, string $orderId): void
    {
        if (!$product->track_inventory) {
            return;
        }
        
        $activeRecipe = $product->getActiveRecipe();
        if (!$activeRecipe) {
            Log::warning('Product has track_inventory=true but no active recipe', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'order_id' => $orderId,
            ]);
            return;
        }
        
        $activeRecipe->load('items.inventoryItem');
        if ($activeRecipe->items->isEmpty()) {
            Log::warning('Product recipe has no items', [
                'product_id' => $product->id,
                'recipe_id' => $activeRecipe->id,
                'order_id' => $orderId,
            ]);
            return;
        }
        
        $inventoryService = app(InventoryService::class);
        $yieldQty = $activeRecipe->yield_quantity > 0 ? $activeRecipe->yield_quantity : 1;
        
        foreach ($activeRecipe->items as $recipeItem) {
            $inventoryItem = $recipeItem->inventoryItem;
            if (!$inventoryItem || !$inventoryItem->track_stock) {
                continue;
            }
            
            $quantityNeeded = ($recipeItem->quantity / $yieldQty) * $quantity;
            
            try {
                $inventoryService->processSale($inventoryItem->id, $quantityNeeded, $orderId);
            } catch (\Exception $e) {
                Log::error('Failed to deduct inventory for recipe item', [
                    'product_id' => $product->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity_needed' => $quantityNeeded,
                    'error' => $e->getMessage(),
                    'order_id' => $orderId,
                ]);
                throw new \Exception("Insufficient stock for product: {$product->name}. {$e->getMessage()}");
            }
        }
    }
    
    /**
     * Restore inventory for a product through its recipe.
     * 
     * @param Product $product
     * @param float $quantity Product quantity to restore
     * @param string|null $orderId Order ID for reference
     */
    private function restoreInventoryForProduct(Product $product, float $quantity, ?string $orderId = null): void
    {
        if (!$product->track_inventory) {
            return;
        }
        
        $activeRecipe = $product->getActiveRecipe();
        if (!$activeRecipe) {
            Log::warning('Product has track_inventory=true but no active recipe for restoration', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'order_id' => $orderId,
            ]);
            return;
        }
        
        $activeRecipe->load('items.inventoryItem');
        if ($activeRecipe->items->isEmpty()) {
            return;
        }
        
        $inventoryService = app(InventoryService::class);
        $yieldQty = $activeRecipe->yield_quantity > 0 ? $activeRecipe->yield_quantity : 1;
        
        foreach ($activeRecipe->items as $recipeItem) {
            $inventoryItem = $recipeItem->inventoryItem;
            if (!$inventoryItem || !$inventoryItem->track_stock) {
                continue;
            }
            
            $quantityToRestore = ($recipeItem->quantity / $yieldQty) * $quantity;
            
            try {
                $inventoryService->adjustStock(
                    $inventoryItem->id,
                    $quantityToRestore,
                    'Order cancelled/updated - stock restoration',
                    null,
                    "Restored from order item"
                );
            } catch (\Exception $e) {
                Log::error('Failed to restore inventory for recipe item', [
                    'product_id' => $product->id,
                    'inventory_item_id' => $inventoryItem->id,
                    'quantity_to_restore' => $quantityToRestore,
                    'error' => $e->getMessage(),
                    'order_id' => $orderId,
                ]);
                // Don't throw exception for restoration failures, just log
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
            if (!$product || !$product->track_inventory) {
                continue;
            }
            
            // Get active recipe for product
            $activeRecipe = $product->getActiveRecipe();
            if (!$activeRecipe) {
                Log::warning('Product has track_inventory=true but no active recipe for restoration', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'order_id' => $item->order_id ?? null,
                ]);
                continue;
            }
            
            // Load recipe items with inventory items
            $activeRecipe->load('items.inventoryItem');
            if ($activeRecipe->items->isEmpty()) {
                continue;
            }
            
            // Calculate quantity to restore for each inventory item based on recipe
            $inventoryService = app(InventoryService::class);
            
            foreach ($activeRecipe->items as $recipeItem) {
                $inventoryItem = $recipeItem->inventoryItem;
                if (!$inventoryItem || !$inventoryItem->track_stock) {
                    continue;
                }
                
                // Calculate quantity to restore: (recipe_item.quantity / recipe.yield_quantity) * order_item.quantity
                $yieldQty = $activeRecipe->yield_quantity > 0 ? $activeRecipe->yield_quantity : 1;
                $quantityToRestore = ($recipeItem->quantity / $yieldQty) * $item->quantity;
                
                try {
                    // Restore stock using InventoryService (adjustment in)
                    $inventoryService->adjustStock(
                        $inventoryItem->id,
                        $quantityToRestore, // Positive for restoration
                        'Order cancelled - stock restoration',
                        null,
                        "Restored from cancelled order item"
                    );
                    
                    Log::info('Inventory restored via recipe', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'inventory_item_id' => $inventoryItem->id,
                        'inventory_item_name' => $inventoryItem->name,
                        'quantity_restored' => $quantityToRestore,
                        'order_item_quantity' => $item->quantity,
                        'order_id' => $item->order_id ?? null,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to restore inventory for recipe item', [
                        'product_id' => $product->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity_to_restore' => $quantityToRestore,
                        'error' => $e->getMessage(),
                        'order_id' => $item->order_id ?? null,
                    ]);
                    // Don't throw exception for restoration failures, just log
                }
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
                        $this->restoreInventoryForProduct($product, $oldItem->quantity, $order->id);
                        
                        Log::info("✅ Restored stock for deleted item", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $oldItem->quantity,
                            'order_id' => $order->id
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
                        $this->deductInventoryForProduct($product, $diff, $order->id);
                        
                        Log::info("✅ Deducted stock for increased quantity", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_diff' => $diff,
                            'order_id' => $order->id
                        ]);
                    } elseif ($diff < 0) {
                        // Quantity berkurang, kembalikan stock
                        $this->restoreInventoryForProduct($product, abs($diff), $order->id);
                        
                        Log::info("✅ Restored stock for decreased quantity", [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_diff' => abs($diff),
                            'order_id' => $order->id
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
                    $this->deductInventoryForProduct($product, $quantity, $order->id);
                    
                    Log::info("✅ Deducted stock for new item", [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'order_id' => $order->id
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
