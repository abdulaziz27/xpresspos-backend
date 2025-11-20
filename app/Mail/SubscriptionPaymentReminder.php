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

class SubscriptionPaymentReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Subscription $subscription,
        public ?SubscriptionPayment $upcomingPayment = null,
        public int $daysUntilExpiration = 7
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
            subject: "Payment Reminder - {$planName} Subscription Expires in {$this->daysUntilExpiration} Days | XpressPOS",
            tags: ['subscription-payment', 'payment-reminder'],
            metadata: [
                'subscription_id' => $this->subscription->id,
                'tenant_id' => $this->subscription->tenant_id,
                'days_until_expiration' => $this->daysUntilExpiration,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-payment-reminder',
            with: [
                'subscription' => $this->subscription,
                'upcomingPayment' => $this->upcomingPayment,
                'storeName' => $this->subscription->store->name,
                'storeEmail' => $this->subscription->store->email,
                'planName' => $this->subscription->plan->name,
                'planPrice' => $this->subscription->plan->price,
                'currentPeriodEnd' => $this->subscription->ends_at,
                'daysUntilExpiration' => $this->daysUntilExpiration,
                'isUrgent' => $this->daysUntilExpiration <= 3,
                'renewalAmount' => $this->upcomingPayment?->amount ?? $this->subscription->amount,
                'billingCycle' => $this->subscription->billing_cycle,
            ],
        );
    }
}