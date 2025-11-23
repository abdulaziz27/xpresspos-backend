<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Enums\PaymentMethodEnum;
use App\Services\PaymentValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['order']);

        // Apply filters
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->input('order_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        if (in_array($sortBy, ['created_at', 'amount', 'payment_method', 'status'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PaymentResource::collection($payments->items()),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Process a payment for an order.
     */
    public function store(ProcessPaymentRequest $request): JsonResponse
    {
        try {
            $orderId = $request->input('order_id');
            // Get user from request (should be set by middleware)
            $user = $request->user() ?? auth()->user();
            
            if (!$user) {
                Log::warning('Payment creation attempted without authentication', [
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
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 401);
            }
            
            $storeContext = \App\Services\StoreContext::instance();
            $currentStoreId = $storeContext->current($user);
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
                    ->with(['items', 'store'])
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
                
                $order = Order::with(['items', 'store'])->find($orderId);
            }
            
            // If order not found, provide helpful error message
            if (!$order) {
                // Check if order exists at all (for better error message)
                $orderExists = Order::withoutGlobalScopes()
                    ->where('id', $orderId)
                    ->first();
                
                if ($orderExists) {
                    $orderStoreId = $orderExists->store_id;
                    $hasAccess = in_array($orderStoreId, $userStores);
                    
                    Log::warning('Order found but not accessible', [
                        'order_id' => $orderId,
                        'user_id' => $user->id,
                        'is_owner' => $isOwner,
                        'current_store_id' => $currentStoreId,
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
                                'details' => 'The order belongs to a different store. Please check your store context.',
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
                    $order = Order::with(['items', 'store'])->find($orderId);
                    
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
                Log::info('StoreContext set to order store for payment', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'store_id' => $order->store_id,
                ]);
            }
            
            $this->authorize('update', $order);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'You do not have permission to update this order.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 403);
        }
        
        // Ensure order totals are calculated (in case items were added but totals not updated)
        if ($order->items->count() > 0 && (!$order->total_amount || $order->total_amount == 0)) {
            $calculationService = app(\App\Services\OrderCalculationService::class);
            $calculationService->updateOrderTotals($order);
            $order->refresh();
        }

        // Check if this is a pending payment for open bill
        $isPendingPayment = $request->input('payment_method') === 'pending' 
                            && $request->input('status') === 'pending';

        if (!$isPendingPayment) {
            // Normal payment - validate balance
            $validationService = app(PaymentValidationService::class);
            $validation = $validationService->validatePayment($order, $request->validated());

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYMENT_VALIDATION_FAILED',
                        'message' => 'Payment validation failed.',
                        'details' => $validation['errors']
                    ],
                    'data' => [
                        'order_id' => $order->id,
                        'order_total' => $order->total_amount ?? 0,
                        'remaining_balance' => $validation['remaining_balance'] ?? 0,
                        'requested_amount' => $validation['requested_amount'] ?? 0,
                        'items_count' => $order->items->count(),
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 422);
            }
        } else {
            // Pending payment - log for audit
            Log::info('Creating pending payment for open bill', [
                'order_id' => $order->id,
                'amount' => $request->input('amount'),
                'user_id' => $user->id
            ]);
        }

        try {
            DB::beginTransaction();

            // For cash payments, handle overpayment correctly
            $paymentMethod = $request->input('payment_method');
            $requestedAmount = $request->input('amount');
            $receivedAmount = $request->input('received_amount');
            $remainingBalance = $order->getRemainingBalance();
            
            // For cash payments: 
            // - amount = jumlah yang dibayar untuk order (harus = remaining_balance, tidak boleh lebih)
            // - received_amount = jumlah yang diterima dari customer (untuk kembalian)
            // - change_amount = received_amount - amount (kembalian)
            if ($paymentMethod === 'cash') {
                // Payment amount should always be the remaining balance (not more, not less)
                $actualPaymentAmount = $remainingBalance;
                
                // If received_amount is provided, use it; otherwise use requested amount as received_amount
                if ($receivedAmount !== null && $receivedAmount > 0) {
                    $actualReceivedAmount = $receivedAmount;
                } else {
                    // No received_amount provided, use requested amount as received_amount
                    $actualReceivedAmount = $requestedAmount;
                }
                
                // Ensure received_amount is at least the payment amount
                if ($actualReceivedAmount < $actualPaymentAmount) {
                    $actualReceivedAmount = $actualPaymentAmount;
                }
            } else {
                // For non-cash payments, amount must match remaining balance
                $actualPaymentAmount = $requestedAmount;
                $actualReceivedAmount = $receivedAmount ?? $requestedAmount;
            }
            
            $payment = $order->payments()->create([
                'store_id' => $order->store_id,
                'payment_method' => $paymentMethod,
                'amount' => $actualPaymentAmount,
                'received_amount' => $actualReceivedAmount,
                'reference_number' => $request->input('reference_number'),
                'status' => $isPendingPayment ? 'pending' : 'pending',
                'notes' => $request->input('notes'),
            ]);

            // Only process payment for non-pending payments
            if (!$isPendingPayment) {
                // Process payment based on method
                $this->processPaymentByMethod($payment, $request->validated());

                // Check if order is now fully paid and complete it
                $order = $order->fresh();
                if ($order->isFullyPaid() && $order->status !== 'completed') {
                    $order->complete();
                }
            }

            DB::commit();

            $payment->load('order');

            Log::info('Payment created successfully', [
                'payment_id' => $payment->id,
                'is_pending' => $isPendingPayment,
                'order_status' => $order->fresh()->status
            ]);

            // Calculate change amount for cash payments
            $changeAmount = 0;
            if ($paymentMethod === 'cash' && $payment->received_amount > $payment->amount) {
                $changeAmount = $payment->received_amount - $payment->amount;
            }
            
            return response()->json([
                'success' => true,
                'data' => new PaymentResource($payment),
                'message' => $isPendingPayment ? 'Pending payment created for open bill' : 'Payment processed successfully',
                'meta' => [
                    'order_total' => $order->total_amount,
                    'total_paid' => $order->payments()->where('status', 'completed')->sum('amount'),
                    'remaining_amount' => $order->fresh()->getRemainingBalance(),
                    'change_amount' => $changeAmount,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $userId = $user->id ?? (auth()->check() ? auth()->id() : null);
            Log::error('Payment processing failed', [
                'order_id' => $order->id,
                'payment_method' => $request->input('payment_method'),
                'amount' => $request->input('amount'),
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_PROCESSING_FAILED',
                    'message' => 'Failed to process payment. Please try again.',
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
     * Display the specified payment.
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
        
        $userStores = $user->stores()->pluck('stores.id')->toArray();
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;
        
        // Find payment - for owner, bypass StoreScope; for others, use normal scope
        if ($isOwner && !empty($userStores)) {
            $payment = Payment::withoutGlobalScopes()
                ->where('id', $id)
                ->whereIn('store_id', $userStores)
                ->with(['order', 'refunds'])
                ->first();
        } else {
            $payment = Payment::with(['order', 'refunds'])->find($id);
        }
        
        if (!$payment || !in_array($payment->store_id, $userStores)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_NOT_FOUND',
                    'message' => 'Payment not found.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }
        
        $this->authorize('view', $payment);

        $payment->load(['order', 'refunds']);

        return response()->json([
            'success' => true,
            'data' => new PaymentResource($payment),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get payment methods configuration.
     */
    public function paymentMethods(): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $methods = PaymentMethodEnum::getAll();

        return response()->json([
            'success' => true,
            'data' => $methods,
            'message' => 'Payment methods retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Get payments summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $date = $request->input('date', now()->toDateString());
        
        $baseQuery = Payment::whereDate('created_at', $date);

        $summary = [
            'total_payments' => (clone $baseQuery)->count(),
            'completed_payments' => (clone $baseQuery)->where('status', 'completed')->count(),
            'pending_payments' => (clone $baseQuery)->where('status', 'pending')->count(),
            'failed_payments' => (clone $baseQuery)->where('status', 'failed')->count(),
            'total_amount' => (clone $baseQuery)->where('status', 'completed')->sum('amount'),
            'cash_payments' => (clone $baseQuery)->where('payment_method', 'cash')->where('status', 'completed')->sum('amount'),
            'card_payments' => (clone $baseQuery)->whereIn('payment_method', ['credit_card', 'debit_card'])->where('status', 'completed')->sum('amount'),
            'digital_payments' => (clone $baseQuery)->whereIn('payment_method', ['qris', 'e_wallet'])->where('status', 'completed')->sum('amount'),
            'bank_transfer_payments' => (clone $baseQuery)->where('payment_method', 'bank_transfer')->where('status', 'completed')->sum('amount'),
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
     * Generate receipt for an order.
     */
    public function receipt(Request $request): JsonResponse
    {
        $orderId = $request->input('order_id');
        // Get user from request (should be set by middleware)
        $user = $request->user() ?? auth()->user();
        
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
        
        // Find order with proper store context handling
        $userStores = $user->stores()->pluck('stores.id')->toArray();
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;
        
        if ($isOwner && !empty($userStores)) {
            $order = Order::withoutGlobalScopes()
                ->where('id', $orderId)
                ->whereIn('store_id', $userStores)
                ->first();
        } else {
            $order = Order::find($orderId);
        }
        
        if (!$order || !in_array($order->store_id, $userStores)) {
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
        
        $this->authorize('view', $order);

        if ($order->status !== 'completed') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_COMPLETED',
                    'message' => 'Receipt can only be generated for completed orders.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        $order->load(['items.product', 'payments', 'member', 'table', 'user:id,name']);

        $receipt = [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $order->created_at->format('Y-m-d H:i:s'),
                'cashier' => $order->user->name,
                'table' => $order->table ? $order->table->table_number : null,
                'member' => $order->member ? [
                    'name' => $order->member->name,
                    'member_number' => $order->member->member_number,
                ] : null,
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'options' => $item->product_options,
                    'notes' => $item->notes,
                ];
            }),
            'totals' => [
                'subtotal' => $order->subtotal,
                'tax_amount' => $order->tax_amount,
                'service_charge' => $order->service_charge,
                'discount_amount' => $order->discount_amount,
                'total_amount' => $order->total_amount,
            ],
            'payments' => $order->payments->map(function ($payment) {
                return [
                    'method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference_number,
                    'processed_at' => $payment->processed_at?->format('Y-m-d H:i:s'),
                ];
            }),
            'store' => [
                'name' => $order->store->name ?? 'POS Store',
                'address' => $order->store->address ?? '',
                'phone' => $order->store->phone ?? '',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $receipt,
            'message' => 'Receipt generated successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ]);
    }

    /**
     * Process payment based on method.
     */
    private function processPaymentByMethod(Payment $payment, array $data): void
    {
        switch ($payment->payment_method) {
            case 'cash':
                $this->processCashPayment($payment, $data);
                break;
            case 'credit_card':
            case 'debit_card':
                $this->processCardPayment($payment, $data);
                break;
            case 'qris':
                $this->processQrisPayment($payment, $data);
                break;
            case 'bank_transfer':
                $this->processBankTransferPayment($payment, $data);
                break;
            case 'e_wallet':
                $this->processEWalletPayment($payment, $data);
                break;
            default:
                throw new \InvalidArgumentException('Unsupported payment method: ' . $payment->payment_method);
        }
    }

    /**
     * Process cash payment.
     */
    private function processCashPayment(Payment $payment, array $data): void
    {
        // Cash payments are immediately processed
        $payment->markAsProcessed();
    }

    /**
     * Process card payment.
     */
    private function processCardPayment(Payment $payment, array $data): void
    {
        // In a real implementation, this would integrate with a payment gateway
        // For now, we'll simulate successful processing
        $payment->markAsProcessed();
    }

    /**
     * Process QRIS payment.
     */
    private function processQrisPayment(Payment $payment, array $data): void
    {
        // In a real implementation, this would integrate with QRIS providers
        // For now, we'll simulate successful processing
        $payment->markAsProcessed();
    }

    /**
     * Process bank transfer payment.
     */
    private function processBankTransferPayment(Payment $payment, array $data): void
    {
        // Bank transfers might need manual verification
        // For now, we'll mark as processed
        $payment->markAsProcessed();
    }

    /**
     * Process e-wallet payment.
     */
    private function processEWalletPayment(Payment $payment, array $data): void
    {
        // In a real implementation, this would integrate with e-wallet providers
        // For now, we'll simulate successful processing
        $payment->markAsProcessed();
    }

    /**
     * Complete an open bill order with payment.
     */
    public function completeOpenBill(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'payment_method' => 'required|string|in:cash,credit_card,debit_card,qris,e_wallet,bank_transfer',
            'amount' => 'required|numeric|min:0.01',
            'received_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $orderId = $request->input('order_id');
        // Get user from request (should be set by middleware)
        $user = $request->user() ?? auth()->user();
        
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
        
        // Find order with proper store context handling
        $userStores = $user->stores()->pluck('stores.id')->toArray();
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;
        
        if ($isOwner && !empty($userStores)) {
            $order = Order::withoutGlobalScopes()
                ->where('id', $orderId)
                ->whereIn('store_id', $userStores)
                ->first();
        } else {
            $order = Order::find($orderId);
        }
        
        if (!$order || !in_array($order->store_id, $userStores)) {
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
        
        $this->authorize('update', $order);

        // Find pending payment (only one should exist per order)
        $pendingPayment = Payment::where('order_id', $order->id)
            ->where('status', 'pending')
            ->first();

        if (!$pendingPayment) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_PENDING_PAYMENT',
                    'message' => 'No pending payment found for this order.',
                    'details' => 'Cannot complete open bill without a pending payment. Please create a pending payment first.'
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();
            
            Log::info('Completing open bill payment', [
                'order_id' => $order->id,
                'payment_id' => $pendingPayment->id,
                'payment_method' => $request->input('payment_method'),
                'user_id' => $user->id
            ]);

            // Update pending payment with actual payment method and details
            $pendingPayment->update([
                'payment_method' => $request->input('payment_method'),
                'amount' => $request->input('amount'),
                'received_amount' => $request->input('received_amount', $request->input('amount')),
                'status' => 'pending', // Will be marked as processed by processPaymentByMethod
                'notes' => $request->input('notes'),
            ]);

            // Process payment based on method
            $this->processPaymentByMethod($pendingPayment, $request->all());

            // Check if order is fully paid and complete it
            $order = $order->fresh();
            if ($order->isFullyPaid() && $order->status !== 'completed') {
                $order->complete();
            }

            DB::commit();
            
            $pendingPayment->load('order');
            
            Log::info('Open bill payment completed successfully', [
                'payment_id' => $pendingPayment->id,
                'order_status' => $order->status,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => new PaymentResource($pendingPayment->fresh()),
                'message' => 'Open bill payment completed successfully',
                'meta' => [
                    'order_total' => $order->total_amount,
                    'total_paid' => $order->payments()->where('status', 'completed')->sum('amount'),
                    'remaining_amount' => max(0, $order->total_amount - $order->payments()->where('status', 'completed')->sum('amount')),
                    'order_status' => $order->status,
                    'change_amount' => $request->input('received_amount') 
                        ? max(0, $request->input('received_amount') - $request->input('amount'))
                        : 0,
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Open bill payment processing failed', [
                'order_id' => $order->id,
                'payment_id' => $pendingPayment->id ?? null,
                'payment_method' => $request->input('payment_method'),
                'amount' => $request->input('amount'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_PROCESSING_FAILED',
                    'message' => 'Failed to process payment. Please try again.',
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
     * Process refund for a payment.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        // Get authenticated user
        $user = $request->user() ?? auth()->user();
        
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
        
        // Find payment - for owner, bypass StoreScope; for others, use normal scope
        if ($isOwner && !empty($userStores)) {
            // Owner: query without StoreScope, then verify access
            $payment = Payment::withoutGlobalScopes()
                ->where('id', $id)
                ->whereIn('store_id', $userStores)
                ->first();
            
            // Also filter by tenant_id for security
            if ($payment) {
                $tenantId = $user->currentTenantId();
                if ($tenantId) {
                    // Get order to check tenant_id
                    $order = Order::withoutGlobalScopes()
                        ->where('id', $payment->order_id)
                        ->first();
                    if ($order && $order->tenant_id !== $tenantId) {
                        $payment = null;
                    }
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
            
            $payment = Payment::find($id);
        }
        
        // If payment not found, provide helpful error message
        if (!$payment) {
            // Check if payment exists at all (for better error message)
            $paymentExists = Payment::withoutGlobalScopes()
                ->where('id', $id)
                ->first();
            
            if ($paymentExists) {
                $paymentStoreId = $paymentExists->store_id;
                $hasAccess = in_array($paymentStoreId, $userStores);
                
                Log::warning('Payment found but not accessible', [
                    'payment_id' => $id,
                    'user_id' => $user->id,
                    'is_owner' => $isOwner,
                    'payment_store_id' => $paymentStoreId,
                    'user_stores' => $userStores,
                    'has_access' => $hasAccess,
                ]);
                
                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'PAYMENT_ACCESS_DENIED',
                            'message' => 'Payment not found or you do not have access to this payment.',
                            'details' => 'The payment belongs to a different store.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1'
                        ]
                    ], 403);
                }
                
                // Payment exists and user has access, but StoreContext might be wrong
                // Set StoreContext to payment's store and retry
                $storeContext->setForUser($user, $paymentStoreId);
                $payment = Payment::find($id);
                
                if (!$payment) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'PAYMENT_ACCESS_DENIED',
                            'message' => 'Payment not found or you do not have access to this payment.',
                            'details' => 'Unable to access payment. Please try again.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1'
                        ]
                    ], 403);
                }
            } else {
                // Payment doesn't exist at all
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYMENT_NOT_FOUND',
                        'message' => 'Payment not found.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1'
                    ]
                ], 404);
            }
        }
        
        // Verify user has access to this payment's store
        if (!in_array($payment->store_id, $userStores)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_ACCESS_DENIED',
                    'message' => 'Payment not found or you do not have access to this payment.',
                    'details' => 'The payment belongs to a different store.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 403);
        }
        
        // Set StoreContext to payment's store to ensure consistency
        if ($storeContext->current($user) !== $payment->store_id) {
            $storeContext->setForUser($user, $payment->store_id);
            Log::info('StoreContext set to payment store for refund', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'store_id' => $payment->store_id,
            ]);
        }
        
        $this->authorize('update', $payment);

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        // Validate refund using PaymentValidationService
        $validationService = app(PaymentValidationService::class);
        $validation = $validationService->validateRefund($payment, $request->input('amount'));

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REFUND_VALIDATION_FAILED',
                    'message' => 'Refund validation failed.',
                    'details' => $validation['errors']
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create refund record (auto-approved and processed)
            $refund = $payment->refunds()->create([
                'store_id' => $payment->store_id,
                'order_id' => $payment->order_id,
                'user_id' => $user->id,
                'amount' => $request->input('amount'),
                'reason' => $request->input('reason'),
                'status' => 'processed', // Auto-approved and processed immediately
                'approved_by' => $user->id,
                'approved_at' => now(),
                'processed_at' => now(),
            ]);

            // Reload order to get fresh data with refunds
            $payment->load(['order.refunds', 'order.payments']);
            $order = $payment->order;
            if ($order) {
                // Calculate total refunded amount
                $totalRefunded = $order->getTotalRefunded();
                $totalPaid = $order->getTotalPaid();
                
                // If all payments are refunded (total refunded >= total paid), cancel the order
                if ($totalRefunded >= $totalPaid && $totalPaid > 0) {
                    $order->update([
                        'status' => 'cancelled',
                        'notes' => $order->notes ? 
                            $order->notes . "\n\nOrder cancelled due to full refund on " . now()->format('Y-m-d H:i:s') : 
                            "Order cancelled due to full refund on " . now()->format('Y-m-d H:i:s')
                    ]);
                    
                    Log::info('Order cancelled due to full refund', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_paid' => $totalPaid,
                        'total_refunded' => $totalRefunded,
                        'refund_id' => $refund->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $refund,
                'message' => 'Refund processed successfully',
                'meta' => [
                    'refunded_amount' => $request->input('amount'),
                    'remaining_refundable' => $payment->fresh()->getRefundableAmount(),
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund processing failed', [
                'payment_id' => $payment->id,
                'amount' => $request->input('amount'),
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REFUND_PROCESSING_FAILED',
                    'message' => 'Failed to process refund. Please try again.',
                    'details' => config('app.debug') ? $e->getMessage() : null
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 500);
        }
    }}
