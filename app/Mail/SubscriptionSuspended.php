<?php

namespace App\Mail;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuspended extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Subscription $subscription,
        public ?SubscriptionPayment $failedPayment = null,
        public ?string $suspensionReason = null,
        public int $gracePeriodDays = 7
    ) {
        $this->onQueue('emails');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $storeName = $this->subscription->store->name;
        $planName = $this->subscription->plan->name;
        
        return new Envelope(
            subject: "Service Suspended - {$planName} Subscription | XpressPOS",
            tags: ['subscription-payment', 'service-suspended'],
            metadata: [
                'subscription_id' => $this->subscription->id,
                'store_id' => $this->subscription->store_id,
                'suspension_reason' => $this->suspensionReason,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-suspended',
            with: [
                'subscription' => $this->subscription,
                'failedPayment' => $this->failedPayment,
                'storeName' => $this->subscription->store->name,
                'storeEmail' => $this->subscription->store->email,
                'planName' => $this->subscription->plan->name,
                'planPrice' => $this->subscription->plan->price,
                'suspensionReason' => $this->suspensionReason ?? 'Payment failure after multiple retry attempts',
                'suspensionDate' => now(),
                'gracePeriodDays' => $this->gracePeriodDays,
                'gracePeriodEnd' => now()->addDays($this->gracePeriodDays),
                'outstandingAmount' => $this->failedPayment?->amount ?? $this->subscription->amount,
                'billingCycle' => $this->subscription->billing_cycle,
            ],
        );
    }
}