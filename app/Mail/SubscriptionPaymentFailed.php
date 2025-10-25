<?php

namespace App\Mail;

use App\Models\SubscriptionPayment;
use App\Models\LandingSubscription;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaymentFailed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SubscriptionPayment $subscriptionPayment,
        public ?string $failureReason = null,
        public bool $isRetryAttempt = false,
        public int $retryCount = 0
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $customerName = $this->getCustomerName();
        $planName = $this->getPlanName();
        
        return new Envelope(
            subject: "Payment Failed - {$planName} Subscription | XpressPOS",
            tags: ['subscription-payment', 'payment-failed'],
            metadata: [
                'subscription_payment_id' => $this->subscriptionPayment->id,
                'payment_method' => $this->subscriptionPayment->payment_method,
                'amount' => $this->subscriptionPayment->amount,
                'retry_count' => $this->retryCount,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-payment-failed',
            with: [
                'subscriptionPayment' => $this->subscriptionPayment,
                'customerName' => $this->getCustomerName(),
                'customerEmail' => $this->getCustomerEmail(),
                'planName' => $this->getPlanName(),
                'paymentMethod' => $this->subscriptionPayment->getPaymentMethodDisplayName(),
                'transactionId' => $this->subscriptionPayment->xendit_invoice_id,
                'failureReason' => $this->failureReason,
                'isRetryAttempt' => $this->isRetryAttempt,
                'retryCount' => $this->retryCount,
                'maxRetries' => 3, // Configuration value
                'nextRetryDate' => $this->getNextRetryDate(),
                'expirationDate' => $this->subscriptionPayment->expires_at,
                'isRenewal' => $this->isRenewalPayment(),
            ],
        );
    }

    /**
     * Get customer name from landing subscription or subscription.
     */
    private function getCustomerName(): string
    {
        if ($this->subscriptionPayment->landingSubscription) {
            return $this->subscriptionPayment->landingSubscription->name;
        }

        if ($this->subscriptionPayment->subscription?->store) {
            return $this->subscriptionPayment->subscription->store->name;
        }

        return 'Valued Customer';
    }

    /**
     * Get customer email from landing subscription or subscription.
     */
    private function getCustomerEmail(): string
    {
        if ($this->subscriptionPayment->landingSubscription) {
            return $this->subscriptionPayment->landingSubscription->email;
        }

        if ($this->subscriptionPayment->subscription?->store) {
            return $this->subscriptionPayment->subscription->store->email ?? '';
        }

        return '';
    }

    /**
     * Get plan name from subscription or landing subscription.
     */
    private function getPlanName(): string
    {
        if ($this->subscriptionPayment->subscription?->plan) {
            return $this->subscriptionPayment->subscription->plan->name;
        }

        if ($this->subscriptionPayment->landingSubscription) {
            return ucfirst($this->subscriptionPayment->landingSubscription->plan) . ' Plan';
        }

        return 'Subscription Plan';
    }

    /**
     * Check if this is a renewal payment.
     */
    private function isRenewalPayment(): bool
    {
        return !empty($this->subscriptionPayment->subscription_id);
    }

    /**
     * Get next retry date (24 hours from now).
     */
    private function getNextRetryDate(): ?\Carbon\Carbon
    {
        if ($this->retryCount < 3) {
            return now()->addHours(24);
        }

        return null;
    }
}