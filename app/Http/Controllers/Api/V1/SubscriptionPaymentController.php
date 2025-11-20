<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionPaymentController extends Controller
{
    // NOTE: PaymentService (Midtrans) telah dihapus karena tidak digunakan.
    // Invoice payment perlu di-refactor untuk Xendit jika diperlukan.
    
    public function __construct(
        protected InvoiceService $invoiceService,
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Get available plans for subscription.
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'annual_price' => $plan->annual_price,
                    'features' => $plan->features,
                    'limits' => $plan->limits,
                    'is_popular' => $plan->is_popular ?? false,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans,
            ],
            'message' => 'Plans retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Create new subscription with payment.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $user = Auth::user() ?? request()->user();
                $store = $user?->store;
                $plan = Plan::findOrFail($request->plan_id);
                $billingCycle = $request->billing_cycle;
                $paymentMethod = $request->payment_method_id
                    ? PaymentMethod::findOrFail($request->payment_method_id)
                    : null;

                // Create subscription
                $subscription = $this->subscriptionService->createSubscription($store, $plan, [
                    'billing_cycle' => $billingCycle,
                ]);

                // Create initial invoice
                $invoice = $this->invoiceService->createInitialInvoice($subscription);

                // NOTE: PaymentService (Midtrans) telah dihapus.
                // Invoice payment perlu di-refactor untuk Xendit jika diperlukan.
                // Untuk sekarang, invoice dibuat tapi payment belum diproses.

                Log::info('Subscription created with invoice (payment processing disabled)', [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $invoice->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => [
                            'id' => $subscription->id,
                            'plan' => [
                                'name' => $plan->name,
                                'slug' => $plan->slug,
                            ],
                            'billing_cycle' => $billingCycle,
                            'amount' => $subscription->amount,
                            'starts_at' => $subscription->starts_at->toISOString(),
                            'ends_at' => $subscription->ends_at->toISOString(),
                        ],
                        'invoice' => [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'amount' => $invoice->amount,
                            'tax_amount' => $invoice->tax_amount,
                            'total_amount' => $invoice->total_amount,
                            'due_date' => $invoice->due_date->toISOString(),
                        ],
                    ],
                    'message' => 'Subscription created successfully. Please complete payment.',
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                        'request_id' => $request->header('X-Request-ID', uniqid()),
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Subscription creation failed', [
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIPTION_CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }
    }

    /**
     * Pay for existing invoice.
     */
    public function payInvoice(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
        ]);

        try {
            return DB::transaction(function () use ($request, $invoiceId) {
                $invoice = Invoice::with('subscription.store')->findOrFail($invoiceId);

                // Check if user has access to this invoice
                $user = Auth::user() ?? request()->user();
                $userStore = $user?->store;
                $invoiceTenant = $invoice->subscription->tenant;
                
                if (!$userStore || !$invoiceTenant || $userStore->tenant_id !== $invoiceTenant->id) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'INVOICE_ACCESS_DENIED',
                            'message' => 'You do not have access to this invoice.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1',
                            'request_id' => $request->header('X-Request-ID', uniqid()),
                        ]
                    ], 403);
                }

                // Check if invoice is already paid
                if ($invoice->isPaid()) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'INVOICE_ALREADY_PAID',
                            'message' => 'This invoice has already been paid.',
                        ],
                        'meta' => [
                            'timestamp' => now()->toISOString(),
                            'version' => 'v1',
                            'request_id' => $request->header('X-Request-ID', uniqid()),
                        ]
                    ], 422);
                }

                // NOTE: PaymentService (Midtrans) telah dihapus.
                // Invoice payment perlu di-refactor untuk Xendit jika diperlukan.

                Log::info('Invoice payment requested (payment processing disabled)', [
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FEATURE_NOT_AVAILABLE',
                        'message' => 'Invoice payment feature is not available. This feature used Midtrans which has been removed. Please use Xendit for subscription payments.',
                    ],
                    'data' => [
                        'invoice' => [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'amount' => $invoice->amount,
                            'tax_amount' => $invoice->tax_amount,
                            'total_amount' => $invoice->total_amount,
                            'due_date' => $invoice->due_date->toISOString(),
                        ],
                    ],
                    'message' => 'Invoice created but payment processing is not available.',
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                        'request_id' => $request->header('X-Request-ID', uniqid()),
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Invoice payment failed', [
                'invoice_id' => $invoiceId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_FAILED',
                    'message' => $e->getMessage(),
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 422);
        }
    }

    /**
     * Get payment methods for user.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $user = Auth::user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'User not authenticated',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 401);
        }

        $paymentMethods = $user->paymentMethods()
            ->where('gateway', 'midtrans')
            ->get()
            ->map(function ($method) {
                return [
                    'id' => $method->id,
                    'type' => $method->type,
                    'last_four' => $method->last_four,
                    'expires_at' => $method->expires_at?->toISOString(),
                    'is_default' => $method->is_default,
                    'metadata' => $method->metadata,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'payment_methods' => $paymentMethods,
            ],
            'message' => 'Payment methods retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Get subscription invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = Auth::user() ?? request()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'User not authenticated',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 401);
        }

        $store = $user->store;

        if (!$store) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_NOT_FOUND',
                    'message' => 'User is not associated with any store',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $subscription = $store->subscriptions()->where('status', 'active')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'No active subscription found',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 404);
        }

        $invoices = $subscription->invoices()
            ->with('payments')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $invoice->amount,
                    'tax_amount' => $invoice->tax_amount,
                    'total_amount' => $invoice->total_amount,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date->toISOString(),
                    'paid_at' => $invoice->paid_at?->toISOString(),
                    'created_at' => $invoice->created_at->toISOString(),
                    'payments' => $invoice->payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => $payment->amount,
                            'status' => $payment->status,
                            'gateway' => $payment->gateway,
                            'processed_at' => $payment->processed_at?->toISOString(),
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'invoices' => $invoices,
            ],
            'message' => 'Invoices retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }

    /**
     * Get payment status for an invoice.
     */
    public function paymentStatus(Request $request, string $invoiceId): JsonResponse
    {
        $invoice = Invoice::with(['payments', 'subscription.store'])->findOrFail($invoiceId);

        // Check if user has access to this invoice
        $userStore = Auth::user()->store;
        $invoiceTenant = $invoice->subscription->tenant;
        
        if (!$userStore || !$invoiceTenant || $userStore->tenant_id !== $invoiceTenant->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_ACCESS_DENIED',
                    'message' => 'You do not have access to this invoice.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                ]
            ], 403);
        }

        $payments = $invoice->payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'gateway' => $payment->gateway,
                'gateway_transaction_id' => $payment->gateway_transaction_id,
                'processed_at' => $payment->processed_at?->toISOString(),
                'gateway_response' => $payment->gateway_response,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'total_amount' => $invoice->total_amount,
                    'total_paid' => $invoice->total_paid,
                    'remaining_amount' => $invoice->remaining_amount,
                ],
                'payments' => $payments,
            ],
            'message' => 'Payment status retrieved successfully',
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
                'request_id' => $request->header('X-Request-ID', uniqid()),
            ]
        ]);
    }
}
