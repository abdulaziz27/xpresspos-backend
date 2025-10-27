<?php

namespace App\Http\Controllers;

use App\Models\LandingSubscription;
use App\Models\SubscriptionPayment;
use App\Models\Invoice;
use App\Services\XenditService;
use App\Services\SubscriptionActivationService;
use App\Services\SubscriptionRenewalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubscriptionPaymentController extends Controller
{
    protected XenditService $xenditService;
    protected SubscriptionActivationService $activationService;
    protected SubscriptionRenewalService $renewalService;

    public function __construct(
        XenditService $xenditService, 
        SubscriptionActivationService $activationService,
        SubscriptionRenewalService $renewalService
    ) {
        $this->xenditService = $xenditService;
        $this->activationService = $activationService;
        $this->renewalService = $renewalService;
    }

    /**
     * Create a subscription payment for a landing subscription.
     */
    public function createSubscriptionPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'landing_subscription_id' => 'required|exists:landing_subscriptions,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:bank_transfer,e_wallet,qris,credit_card',
            'description' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $landingSubscription = LandingSubscription::findOrFail($request->landing_subscription_id);
            
            // Generate external ID for Xendit
            $externalId = SubscriptionPayment::generateExternalId();
            
            // Create Xendit invoice
            $xenditInvoiceData = [
                'external_id' => $externalId,
                'amount' => $request->amount,
                'description' => $request->description ?? "Subscription payment for {$landingSubscription->email}",
                'customer_name' => $landingSubscription->name,
                'customer_email' => $landingSubscription->email,
                'customer_phone' => $landingSubscription->phone,
                'payment_methods' => [$request->payment_method],
            ];

            $xenditResponse = $this->xenditService->createInvoice($xenditInvoiceData);

            if (!$xenditResponse['success']) {
                throw new \Exception('Failed to create Xendit invoice: ' . ($xenditResponse['error'] ?? 'Unknown error'));
            }

            // Create subscription payment record
            $subscriptionPayment = SubscriptionPayment::create([
                'landing_subscription_id' => $landingSubscription->id,
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'external_id' => $externalId,
                'payment_method' => $request->payment_method,
                'amount' => $request->amount,
                'status' => 'pending',
                'expires_at' => now()->addHours((int) config('xendit.invoice_expiry_hours', 24)),
                'gateway_response' => $xenditResponse,
            ]);

            // Update landing subscription with payment info
            $landingSubscription->update([
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'payment_status' => 'pending',
                'payment_amount' => $request->amount,
            ]);

            Log::info('Subscription payment created', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'landing_subscription_id' => $landingSubscription->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription payment created successfully',
                'data' => [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'xendit_invoice_id' => $xenditResponse['data']['id'],
                    'payment_url' => $xenditResponse['data']['invoice_url'],
                    'expires_at' => $subscriptionPayment->expires_at,
                    'amount' => $subscriptionPayment->amount,
                    'status' => $subscriptionPayment->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create subscription payment', [
                'error' => $e->getMessage(),
                'landing_subscription_id' => $request->landing_subscription_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment status by Xendit invoice ID.
     */
    public function getPaymentStatus(string $xenditInvoiceId): JsonResponse
    {
        try {
            $subscriptionPayment = SubscriptionPayment::where('xendit_invoice_id', $xenditInvoiceId)->first();

            // If no SubscriptionPayment found, check LandingSubscription
            if (!$subscriptionPayment) {
                $landingSubscription = LandingSubscription::where('xendit_invoice_id', $xenditInvoiceId)->first();
                
                if (!$landingSubscription) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment not found'
                    ], 404);
                }

                // Return status based on LandingSubscription
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription_id' => $landingSubscription->id,
                        'xendit_invoice_id' => $landingSubscription->xendit_invoice_id,
                        'status' => $landingSubscription->payment_status ?? 'pending',
                        'amount' => $landingSubscription->payment_amount,
                        'payment_method' => null,
                        'payment_channel' => null,
                        'paid_at' => $landingSubscription->paid_at,
                        'expires_at' => now()->addHours(24), // Default expiry
                        'invoice_url' => null,
                        'created_at' => $landingSubscription->created_at,
                        'updated_at' => $landingSubscription->updated_at,
                    ]
                ]);
            }

            // Get latest status from Xendit
            $xenditInvoice = $this->xenditService->getInvoice($xenditInvoiceId);
            
            // Update local status if different
            if ($xenditInvoice['status'] !== strtoupper($subscriptionPayment->status)) {
                $subscriptionPayment->updateFromXenditCallback($xenditInvoice);
                $subscriptionPayment->refresh();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'xendit_invoice_id' => $subscriptionPayment->xendit_invoice_id,
                    'status' => $subscriptionPayment->status,
                    'amount' => $subscriptionPayment->amount,
                    'payment_method' => $subscriptionPayment->payment_method,
                    'payment_channel' => $subscriptionPayment->payment_channel,
                    'paid_at' => $subscriptionPayment->paid_at,
                    'expires_at' => $subscriptionPayment->expires_at,
                    'gateway_fee' => $subscriptionPayment->gateway_fee,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payment status', [
                'error' => $e->getMessage(),
                'xendit_invoice_id' => $xenditInvoiceId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download invoice PDF for a subscription payment.
     */
    public function downloadInvoice(string $subscriptionPaymentId): JsonResponse
    {
        try {
            $subscriptionPayment = SubscriptionPayment::findOrFail($subscriptionPaymentId);

            if (!$subscriptionPayment->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice can only be downloaded for paid payments'
                ], 400);
            }

            // Get invoice URL from Xendit
            $xenditInvoice = $this->xenditService->getInvoice($subscriptionPayment->xendit_invoice_id);
            
            if (!isset($xenditInvoice['invoice_url'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice URL not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_url' => $xenditInvoice['invoice_url'],
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'amount' => $subscriptionPayment->amount,
                    'paid_at' => $subscriptionPayment->paid_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice download URL', [
                'error' => $e->getMessage(),
                'subscription_payment_id' => $subscriptionPaymentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get invoice download URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry a failed subscription payment.
     */
    public function retryPayment(string $subscriptionPaymentId): JsonResponse
    {
        try {
            $subscriptionPayment = SubscriptionPayment::findOrFail($subscriptionPaymentId);

            if ($subscriptionPayment->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is already paid'
                ], 400);
            }

            if ($subscriptionPayment->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is still pending'
                ], 400);
            }

            // Create new Xendit invoice for retry
            $externalId = SubscriptionPayment::generateExternalId();
            
            $xenditInvoiceData = [
                'external_id' => $externalId,
                'amount' => $subscriptionPayment->amount,
                'description' => "Retry subscription payment for {$subscriptionPayment->landingSubscription->email}",
                'invoice_duration' => config('xendit.invoice_expiry_hours', 24) * 3600,
                'customer' => [
                    'given_names' => $subscriptionPayment->landingSubscription->name,
                    'email' => $subscriptionPayment->landingSubscription->email,
                ],
                'success_redirect_url' => url('/subscription/payment/success'),
                'failure_redirect_url' => url('/subscription/payment/failed'),
                'currency' => config('xendit.currency', 'IDR'),
                'payment_methods' => [$subscriptionPayment->payment_method],
            ];

            $xenditResponse = $this->xenditService->createInvoice($xenditInvoiceData);

            if (!$xenditResponse['success']) {
                throw new \Exception('Failed to create Xendit invoice for retry: ' . ($xenditResponse['error'] ?? 'Unknown error'));
            }

            // Update subscription payment with new Xendit invoice
            $subscriptionPayment->update([
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'external_id' => $externalId,
                'status' => 'pending',
                'expires_at' => now()->addHours((int) config('xendit.invoice_expiry_hours', 24)),
                'gateway_response' => $xenditResponse['data'],
            ]);

            Log::info('Subscription payment retried', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'new_xendit_invoice_id' => $xenditResponse['data']['id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment retry created successfully',
                'data' => [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'xendit_invoice_id' => $xenditResponse['data']['id'],
                    'payment_url' => $xenditResponse['data']['invoice_url'],
                    'expires_at' => $subscriptionPayment->expires_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retry subscription payment', [
                'error' => $e->getMessage(),
                'subscription_payment_id' => $subscriptionPaymentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription payment history for a landing subscription.
     */
    public function getPaymentHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'landing_subscription_id' => 'required|exists:landing_subscriptions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payments = SubscriptionPayment::where('landing_subscription_id', $request->landing_subscription_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'xendit_invoice_id' => $payment->xendit_invoice_id,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'payment_method' => $payment->payment_method,
                        'payment_channel' => $payment->payment_channel,
                        'paid_at' => $payment->paid_at,
                        'expires_at' => $payment->expires_at,
                        'created_at' => $payment->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payment history', [
                'error' => $e->getMessage(),
                'landing_subscription_id' => $request->landing_subscription_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually activate subscription for a paid payment.
     */
    public function activateSubscription(string $subscriptionPaymentId): JsonResponse
    {
        try {
            $subscriptionPayment = SubscriptionPayment::findOrFail($subscriptionPaymentId);

            if (!$subscriptionPayment->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot activate subscription for unpaid payment'
                ], 400);
            }

            $activationResult = $this->activationService->activateSubscription($subscriptionPayment);

            return response()->json([
                'success' => true,
                'message' => $activationResult['already_activated'] 
                    ? 'Subscription was already activated' 
                    : 'Subscription activated successfully',
                'data' => [
                    'store_id' => $activationResult['store']->id,
                    'store_name' => $activationResult['store']->name,
                    'user_id' => $activationResult['user']->id,
                    'user_email' => $activationResult['user']->email,
                    'subscription_id' => $activationResult['subscription']->id,
                    'onboarding_url' => $subscriptionPayment->landingSubscription->onboarding_url,
                    'already_activated' => $activationResult['already_activated'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to activate subscription manually', [
                'error' => $e->getMessage(),
                'subscription_payment_id' => $subscriptionPaymentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activation status for a landing subscription.
     */
    public function getActivationStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'landing_subscription_id' => 'required|exists:landing_subscriptions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $landingSubscription = LandingSubscription::findOrFail($request->landing_subscription_id);
            $activationStatus = $this->activationService->getActivationStatus($landingSubscription);

            return response()->json([
                'success' => true,
                'data' => $activationStatus
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get activation status', [
                'error' => $e->getMessage(),
                'landing_subscription_id' => $request->landing_subscription_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get activation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process renewals for expiring subscriptions.
     */
    public function processRenewals(): JsonResponse
    {
        try {
            $results = $this->renewalService->processRenewals();

            return response()->json([
                'success' => true,
                'message' => 'Renewal processing completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process renewals', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process renewals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create renewal payment for a specific subscription.
     */
    public function createRenewalPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $subscription = \App\Models\Subscription::findOrFail($request->subscription_id);
            
            $result = $this->renewalService->processSubscriptionRenewal($subscription);

            return response()->json([
                'success' => true,
                'message' => 'Renewal payment created successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create renewal payment', [
                'error' => $e->getMessage(),
                'subscription_id' => $request->subscription_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create renewal payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get renewal statistics.
     */
    public function getRenewalStats(): JsonResponse
    {
        try {
            $stats = $this->renewalService->getRenewalStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get renewal stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get renewal stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}