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
        $order = Order::findOrFail($request->input('order_id'));
        $this->authorize('update', $order);

        // Validate payment using PaymentValidationService
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
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $payment = $order->payments()->create([
                'store_id' => $order->store_id,
                'payment_method' => $request->input('payment_method'),
                'amount' => $request->input('amount'),
                'reference_number' => $request->input('reference_number'),
                'status' => 'pending',
                'notes' => $request->input('notes'),
            ]);

            // Process payment based on method
            $this->processPaymentByMethod($payment, $request->validated());

            // Check if order is now fully paid and complete it
            $order = $order->fresh();
            if ($order->isFullyPaid() && $order->status !== 'completed') {
                $order->complete();
            }

            DB::commit();

            $payment->load('order');

            return response()->json([
                'success' => true,
                'data' => new PaymentResource($payment),
                'message' => 'Payment processed successfully',
                'meta' => [
                    'order_total' => $order->total_amount,
                    'total_paid' => $order->payments()->completed()->sum('amount'),
                    'remaining_amount' => max(0, $order->total_amount - $order->payments()->completed()->sum('amount')),
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed', [
                'order_id' => $order->id,
                'payment_method' => $request->input('payment_method'),
                'amount' => $request->input('amount'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
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
        $payment = Payment::with(['order', 'refunds'])->findOrFail($id);
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
        $order = Order::findOrFail($request->input('order_id'));
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
}    /**

     * Process refund for a payment.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
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

            // Create refund record
            $refund = $payment->refunds()->create([
                'store_id' => $payment->store_id,
                'order_id' => $payment->order_id,
                'user_id' => auth()->id(),
                'amount' => $request->input('amount'),
                'reason' => $request->input('reason'),
                'status' => 'completed', // Auto-approve for now
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'processed_at' => now(),
                'processed_by' => auth()->id(),
            ]);

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
                'user_id' => auth()->id()
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
    }