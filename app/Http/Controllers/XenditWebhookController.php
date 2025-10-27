<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\LandingSubscription;
use App\Models\Invoice;
use App\Services\XenditService;
use App\Services\SubscriptionActivationService;
use App\Services\SubscriptionRenewalService;
use App\Services\SubscriptionPaymentNotificationService;
use App\Services\PaymentSecurityService;
use App\Jobs\SendPaymentNotificationJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    protected XenditService $xenditService;
    protected SubscriptionActivationService $activationService;
    protected SubscriptionRenewalService $renewalService;
    protected SubscriptionPaymentNotificationService $notificationService;
    protected PaymentSecurityService $securityService;

    public function __construct(
        XenditService $xenditService, 
        SubscriptionActivationService $activationService,
        SubscriptionRenewalService $renewalService,
        SubscriptionPaymentNotificationService $notificationService,
        PaymentSecurityService $securityService
    ) {
        $this->xenditService = $xenditService;
        $this->activationService = $activationService;
        $this->renewalService = $renewalService;
        $this->notificationService = $notificationService;
        $this->securityService = $securityService;
    }

    /**
     * Handle Xendit invoice callback webhook.
     */
    public function handleInvoiceCallback(Request $request): JsonResponse
    {
        try {
            // Enhanced security logging
            $this->securityService->logWebhookSecurityEvent(
                'webhook_received',
                $request,
                ['webhook_type' => 'invoice'],
                'info'
            );

            // Validate webhook signature (enhanced validation is handled by middleware)
            if (!$this->validateSignature($request)) {
                $this->securityService->logWebhookSecurityEvent(
                    'invalid_signature',
                    $request,
                    ['signature_header' => $request->header('x-callback-token')]
                );
                
                return response()->json(['message' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            
            Log::info('Received Xendit invoice webhook', [
                'invoice_id' => $payload['id'] ?? null,
                'status' => $payload['status'] ?? null,
                'external_id' => $payload['external_id'] ?? null,
            ]);

            // Find subscription payment by Xendit invoice ID
            $subscriptionPayment = SubscriptionPayment::where('xendit_invoice_id', $payload['id'])->first();

            if (!$subscriptionPayment) {
                $this->securityService->logWebhookSecurityEvent(
                    'payment_not_found',
                    $request,
                    [
                        'xendit_invoice_id' => $payload['id'],
                        'external_id' => $payload['external_id'] ?? null,
                    ]
                );
                
                return response()->json(['message' => 'Payment not found'], 404);
            }

            // Store previous status for notification logic
            $previousStatus = $subscriptionPayment->status;

            // Update subscription payment with callback data
            $subscriptionPayment->updateFromXenditCallback($payload);

            // Update landing subscription status
            if ($subscriptionPayment->landingSubscription) {
                $this->updateLandingSubscriptionStatus($subscriptionPayment, $payload);
            }

            // Update invoice status if linked
            if ($subscriptionPayment->invoice) {
                $this->updateInvoiceStatus($subscriptionPayment, $payload);
            }

            // Handle payment completion
            if ($subscriptionPayment->isPaid()) {
                $this->handlePaymentCompletion($subscriptionPayment);
            } elseif ($subscriptionPayment->hasFailed()) {
                $this->handlePaymentFailure($subscriptionPayment);
            }

            // Send real-time payment notification if status changed
            $this->handlePaymentStatusNotification($subscriptionPayment, $previousStatus);

            $this->securityService->logWebhookSecurityEvent(
                'webhook_processed_successfully',
                $request,
                [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'status' => $subscriptionPayment->status,
                    'previous_status' => $previousStatus,
                ],
                'info'
            );

            return response()->json(['message' => 'Webhook processed successfully']);

        } catch (\Exception $e) {
            $this->securityService->logWebhookSecurityEvent(
                'webhook_processing_failed',
                $request,
                [
                    'error' => $e->getMessage(),
                    'payload_keys' => array_keys($request->all()),
                ]
            );

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Xendit recurring payment callback webhook.
     */
    public function handleRecurringCallback(Request $request): JsonResponse
    {
        try {
            // Enhanced security logging
            $this->securityService->logWebhookSecurityEvent(
                'recurring_webhook_received',
                $request,
                ['webhook_type' => 'recurring'],
                'info'
            );

            // Validate webhook signature
            if (!$this->validateSignature($request)) {
                $this->securityService->logWebhookSecurityEvent(
                    'invalid_recurring_signature',
                    $request,
                    ['signature_header' => $request->header('x-callback-token')]
                );
                
                return response()->json(['message' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            
            Log::info('Received Xendit recurring payment webhook', [
                'recurring_payment_id' => $payload['id'] ?? null,
                'status' => $payload['status'] ?? null,
            ]);

            // TODO: Implement recurring payment handling in future tasks
            // This will be used for subscription renewals

            return response()->json(['message' => 'Recurring webhook received']);

        } catch (\Exception $e) {
            Log::error('Failed to process recurring webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['message' => 'Recurring webhook processing failed'], 500);
        }
    }

    /**
     * Validate webhook signature from Xendit.
     */
    protected function validateSignature(Request $request): bool
    {
        $webhookToken = config('xendit.webhook_token');
        
        if (!$webhookToken) {
            Log::warning('Webhook token not configured');
            return false;
        }

        $signature = $request->header('x-callback-token');
        
        if (!$signature) {
            Log::warning('No webhook signature provided');
            return false;
        }

        return hash_equals($webhookToken, $signature);
    }

    /**
     * Update landing subscription status based on payment status.
     */
    protected function updateLandingSubscriptionStatus(SubscriptionPayment $subscriptionPayment, array $payload): void
    {
        $landingSubscription = $subscriptionPayment->landingSubscription;
        
        $updateData = [
            'payment_status' => $subscriptionPayment->status,
        ];

        if ($subscriptionPayment->isPaid()) {
            $updateData['paid_at'] = $subscriptionPayment->paid_at;
            $updateData['status'] = 'paid';
            $updateData['stage'] = 'payment_completed';
        } elseif ($subscriptionPayment->hasFailed() || $subscriptionPayment->hasExpired()) {
            $updateData['status'] = 'payment_failed';
            $updateData['stage'] = 'payment_failed';
        }

        $landingSubscription->update($updateData);

        // Trigger automatic account provisioning for successful payments
        if ($subscriptionPayment->isPaid() && !$landingSubscription->provisioned_user_id) {
            $this->triggerAccountProvisioning($landingSubscription);
        }

        Log::info('Landing subscription status updated', [
            'landing_subscription_id' => $landingSubscription->id,
            'payment_status' => $updateData['payment_status'],
            'status' => $updateData['status'] ?? $landingSubscription->status,
        ]);
    }

    /**
     * Update invoice status based on payment status.
     */
    protected function updateInvoiceStatus(SubscriptionPayment $subscriptionPayment, array $payload): void
    {
        $invoice = $subscriptionPayment->invoice;

        if ($subscriptionPayment->isPaid()) {
            $invoice->markAsPaid();
            Log::info('Invoice marked as paid', ['invoice_id' => $invoice->id]);
        } elseif ($subscriptionPayment->hasFailed()) {
            $invoice->markAsFailed();
            Log::info('Invoice marked as failed', ['invoice_id' => $invoice->id]);
        }
    }

    /**
     * Handle payment completion tasks.
     */
    protected function handlePaymentCompletion(SubscriptionPayment $subscriptionPayment): void
    {
        try {
            // Check if this is a renewal payment or new subscription
            if ($subscriptionPayment->subscription_id) {
                // This is a renewal payment
                $this->renewalService->handleRenewalPayment($subscriptionPayment);
                
                Log::info('Renewal payment processed successfully', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'subscription_id' => $subscriptionPayment->subscription_id,
                ]);

                // Renewal confirmation email will be handled by notification system
            } else {
                // This is a new subscription activation
                $activationResult = $this->activationService->activateSubscription($subscriptionPayment);

                if ($activationResult['already_activated']) {
                    Log::info('Subscription was already activated', [
                        'subscription_payment_id' => $subscriptionPayment->id,
                        'store_id' => $activationResult['store']->id,
                    ]);
                } else {
                    Log::info('Subscription activated successfully', [
                        'subscription_payment_id' => $subscriptionPayment->id,
                        'store_id' => $activationResult['store']->id,
                        'user_id' => $activationResult['user']->id,
                        'subscription_id' => $activationResult['subscription']->id,
                    ]);

                    // Send welcome email with payment confirmation will be handled by notification system
                    // TODO: Send SMS notification if phone number available
                    // TODO: Create default store data (categories, sample products)
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle payment completion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'subscription_payment_id' => $subscriptionPayment->id,
            ]);

            // Don't throw exception to avoid webhook retry loops
            // The payment is still marked as paid, activation can be retried manually
        }
    }

    /**
     * Handle payment failure tasks.
     */
    protected function handlePaymentFailure(SubscriptionPayment $subscriptionPayment): void
    {
        try {
            // Check if this is a renewal payment failure
            if ($subscriptionPayment->subscription_id) {
                $this->renewalService->handleFailedRenewal($subscriptionPayment);
                
                Log::info('Renewal payment failure handled', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'subscription_id' => $subscriptionPayment->subscription_id,
                ]);
            }

            // Payment failure notification will be handled by notification system

        } catch (\Exception $e) {
            Log::error('Failed to handle payment failure', [
                'error' => $e->getMessage(),
                'subscription_payment_id' => $subscriptionPayment->id,
            ]);
        }
    }

    /**
     * Handle payment status change notifications.
     */
    protected function handlePaymentStatusNotification(SubscriptionPayment $subscriptionPayment, string $previousStatus): void
    {
        try {
            // Only send notifications for status changes
            if ($previousStatus === $subscriptionPayment->status) {
                return;
            }

            // Dispatch notification job based on new status
            if ($subscriptionPayment->isPaid()) {
                SendPaymentNotificationJob::dispatchPaymentConfirmation($subscriptionPayment);
                
                Log::info('Payment confirmation notification dispatched', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
            } elseif ($subscriptionPayment->hasFailed()) {
                $failureReason = $this->extractFailureReason($subscriptionPayment);
                
                SendPaymentNotificationJob::dispatchPaymentFailure(
                    $subscriptionPayment,
                    $failureReason
                );
                
                Log::info('Payment failure notification dispatched', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'failure_reason' => $failureReason,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to dispatch payment notification', [
                'error' => $e->getMessage(),
                'subscription_payment_id' => $subscriptionPayment->id,
                'previous_status' => $previousStatus,
                'current_status' => $subscriptionPayment->status,
            ]);
        }
    }

    /**
     * Extract failure reason from gateway response.
     */
    protected function extractFailureReason(SubscriptionPayment $subscriptionPayment): ?string
    {
        $gatewayResponse = $subscriptionPayment->gateway_response;
        
        if (!$gatewayResponse || !is_array($gatewayResponse)) {
            return null;
        }

        // Extract failure reason from Xendit response
        if (isset($gatewayResponse['failure_code'])) {
            return $this->mapXenditFailureCode($gatewayResponse['failure_code']);
        }

        if (isset($gatewayResponse['status']) && $gatewayResponse['status'] === 'EXPIRED') {
            return 'Payment expired - please try again with a new payment link';
        }

        return 'Payment processing failed - please contact support if the issue persists';
    }

    /**
     * Map Xendit failure codes to user-friendly messages.
     */
    protected function mapXenditFailureCode(string $failureCode): string
    {
        return match($failureCode) {
            'INSUFFICIENT_BALANCE' => 'Insufficient funds in your account',
            'INVALID_CARD' => 'Invalid card details provided',
            'EXPIRED_CARD' => 'Your card has expired',
            'CARD_DECLINED' => 'Your card was declined by the bank',
            'PROCESSING_ERROR' => 'Payment processing error - please try again',
            'FRAUD_DETECTED' => 'Payment blocked for security reasons',
            'LIMIT_EXCEEDED' => 'Transaction limit exceeded',
            default => "Payment failed: {$failureCode}"
        };
    }

    /**
     * Get webhook endpoint information for testing.
     */
    public function getWebhookInfo(): JsonResponse
    {
        return response()->json([
            'webhook_endpoints' => [
                'invoice_callback' => url('/api/webhooks/xendit/invoice'),
                'recurring_callback' => url('/api/webhooks/xendit/recurring'),
            ],
            'required_headers' => [
                'x-callback-token' => 'Your Xendit webhook token',
                'content-type' => 'application/json',
            ],
            'webhook_events' => [
                'invoice.paid',
                'invoice.expired',
                'invoice.failed',
                'recurring_payment.succeeded',
                'recurring_payment.failed',
            ]
        ]);
    }

    /**
     * Trigger automatic account provisioning for successful payments.
     */
    private function triggerAccountProvisioning(LandingSubscription $landingSubscription): void
    {
        try {
            $provisioningService = app(\App\Services\SubscriptionProvisioningService::class);
            $result = $provisioningService->provisionSubscription($landingSubscription);
            
            if ($result['success']) {
                Log::info('Account provisioning successful', [
                    'landing_subscription_id' => $landingSubscription->id,
                    'user_id' => $result['user']->id,
                    'store_id' => $result['store']->id,
                ]);
            } else {
                Log::error('Account provisioning failed', [
                    'landing_subscription_id' => $landingSubscription->id,
                    'error' => $result['error']
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Account provisioning exception', [
                'landing_subscription_id' => $landingSubscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}