<?php

namespace App\Services;

use App\Models\SubscriptionPayment;
use App\Models\Subscription;
use App\Mail\SubscriptionPaymentConfirmation;
use App\Mail\SubscriptionPaymentFailed;
use App\Mail\SubscriptionPaymentReminder;
use App\Mail\SubscriptionSuspended;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentNotificationService
{
    public function __construct(
        private SubscriptionInvoicePdfService $invoicePdfService
    ) {}

    /**
     * Send payment confirmation email with PDF invoice.
     */
    public function sendPaymentConfirmation(SubscriptionPayment $subscriptionPayment): bool
    {
        try {
            $recipientEmail = $this->getRecipientEmail($subscriptionPayment);
            
            if (!$recipientEmail) {
                Log::warning('No recipient email found for payment confirmation', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
                return false;
            }

            // Generate PDF invoice if payment is successful
            $invoicePdfPath = null;
            if ($subscriptionPayment->isPaid()) {
                $invoicePdfPath = $this->invoicePdfService->generateInvoicePdf($subscriptionPayment);
            }

            // Send confirmation email
            Mail::to($recipientEmail)->send(
                new SubscriptionPaymentConfirmation($subscriptionPayment, $invoicePdfPath)
            );

            Log::info('Payment confirmation email sent', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'recipient_email' => $recipientEmail,
                'has_pdf_attachment' => !empty($invoicePdfPath),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment confirmation email', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send payment failure notification email.
     */
    public function sendPaymentFailure(
        SubscriptionPayment $subscriptionPayment,
        ?string $failureReason = null,
        bool $isRetryAttempt = false,
        int $retryCount = 0
    ): bool {
        try {
            $recipientEmail = $this->getRecipientEmail($subscriptionPayment);
            
            if (!$recipientEmail) {
                Log::warning('No recipient email found for payment failure notification', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
                return false;
            }

            // Send failure notification email
            Mail::to($recipientEmail)->send(
                new SubscriptionPaymentFailed(
                    $subscriptionPayment,
                    $failureReason,
                    $isRetryAttempt,
                    $retryCount
                )
            );

            Log::info('Payment failure email sent', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'recipient_email' => $recipientEmail,
                'retry_count' => $retryCount,
                'failure_reason' => $failureReason,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment failure email', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send payment reminder email for upcoming subscription renewal.
     */
    public function sendPaymentReminder(
        Subscription $subscription,
        ?SubscriptionPayment $upcomingPayment = null,
        int $daysUntilExpiration = 7
    ): bool {
        try {
            $recipientEmail = $this->getSubscriptionRecipientEmail($subscription);
            
            if (!$recipientEmail) {
                Log::warning('No recipient email found for payment reminder', [
                    'subscription_id' => $subscription->id,
                ]);
                return false;
            }

            // Send reminder email
            Mail::to($recipientEmail)->send(
                new SubscriptionPaymentReminder(
                    $subscription,
                    $upcomingPayment,
                    $daysUntilExpiration
                )
            );

            Log::info('Payment reminder email sent', [
                'subscription_id' => $subscription->id,
                'recipient_email' => $recipientEmail,
                'days_until_expiration' => $daysUntilExpiration,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder email', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send subscription suspension notification email.
     */
    public function sendSubscriptionSuspended(
        Subscription $subscription,
        ?SubscriptionPayment $failedPayment = null,
        ?string $suspensionReason = null,
        int $gracePeriodDays = 7
    ): bool {
        try {
            $recipientEmail = $this->getSubscriptionRecipientEmail($subscription);
            
            if (!$recipientEmail) {
                Log::warning('No recipient email found for suspension notification', [
                    'subscription_id' => $subscription->id,
                ]);
                return false;
            }

            // Send suspension notification email
            Mail::to($recipientEmail)->send(
                new SubscriptionSuspended(
                    $subscription,
                    $failedPayment,
                    $suspensionReason,
                    $gracePeriodDays
                )
            );

            Log::info('Subscription suspension email sent', [
                'subscription_id' => $subscription->id,
                'recipient_email' => $recipientEmail,
                'suspension_reason' => $suspensionReason,
                'grace_period_days' => $gracePeriodDays,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send subscription suspension email', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send bulk payment reminders for subscriptions expiring soon.
     */
    public function sendBulkPaymentReminders(int $daysUntilExpiration = 7): int
    {
        $subscriptions = Subscription::active()
            ->expiringSoon($daysUntilExpiration)
            ->with(['store', 'plan'])
            ->get();

        $sentCount = 0;

        foreach ($subscriptions as $subscription) {
            if ($this->sendPaymentReminder($subscription, null, $daysUntilExpiration)) {
                $sentCount++;
            }

            // Add small delay to avoid overwhelming email service
            usleep(100000); // 0.1 second delay
        }

        Log::info('Bulk payment reminders sent', [
            'total_subscriptions' => $subscriptions->count(),
            'emails_sent' => $sentCount,
            'days_until_expiration' => $daysUntilExpiration,
        ]);

        return $sentCount;
    }

    /**
     * Get recipient email from subscription payment.
     */
    private function getRecipientEmail(SubscriptionPayment $subscriptionPayment): ?string
    {
        // Try landing subscription email first
        if ($subscriptionPayment->landingSubscription?->email) {
            return $subscriptionPayment->landingSubscription->email;
        }

        // Try subscription store email
        if ($subscriptionPayment->subscription?->store?->email) {
            return $subscriptionPayment->subscription->store->email;
        }

        return null;
    }

    /**
     * Get recipient email from subscription.
     */
    private function getSubscriptionRecipientEmail(Subscription $subscription): ?string
    {
        // Try store email
        if ($subscription->store?->email) {
            return $subscription->store->email;
        }

        return null;
    }

    /**
     * Send real-time payment status update (for immediate notifications).
     */
    public function sendRealTimePaymentUpdate(SubscriptionPayment $subscriptionPayment): bool
    {
        if ($subscriptionPayment->isPaid()) {
            return $this->sendPaymentConfirmation($subscriptionPayment);
        } elseif ($subscriptionPayment->hasFailed()) {
            return $this->sendPaymentFailure($subscriptionPayment);
        }

        return false;
    }

    /**
     * Handle webhook payment status change and send appropriate notification.
     */
    public function handlePaymentStatusChange(
        SubscriptionPayment $subscriptionPayment,
        string $previousStatus
    ): bool {
        $currentStatus = $subscriptionPayment->status;

        // Only send notifications for status changes
        if ($previousStatus === $currentStatus) {
            return false;
        }

        Log::info('Payment status changed, sending notification', [
            'subscription_payment_id' => $subscriptionPayment->id,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
        ]);

        return $this->sendRealTimePaymentUpdate($subscriptionPayment);
    }
}