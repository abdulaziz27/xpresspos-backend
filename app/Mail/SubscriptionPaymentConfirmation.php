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
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class SubscriptionPaymentConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SubscriptionPayment $subscriptionPayment,
        public ?string $invoicePdfPath = null
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
            subject: "Payment Confirmation - {$planName} Subscription | XpressPOS",
            tags: ['subscription-payment', 'payment-confirmation'],
            metadata: [
                'subscription_payment_id' => $this->subscriptionPayment->id,
                'payment_method' => $this->subscriptionPayment->payment_method,
                'amount' => $this->subscriptionPayment->amount,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-payment-confirmation',
            with: [
                'subscriptionPayment' => $this->subscriptionPayment,
                'customerName' => $this->getCustomerName(),
                'customerEmail' => $this->getCustomerEmail(),
                'planName' => $this->getPlanName(),
                'planPrice' => $this->getPlanPrice(),
                'paymentMethod' => $this->subscriptionPayment->getPaymentMethodDisplayName(),
                'transactionId' => $this->subscriptionPayment->xendit_invoice_id,
                'paidAt' => $this->subscriptionPayment->paid_at,
                'subscriptionStartDate' => $this->getSubscriptionStartDate(),
                'subscriptionEndDate' => $this->getSubscriptionEndDate(),
                'hasInvoicePdf' => !empty($this->invoicePdfPath) && Storage::exists($this->invoicePdfPath),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        $attachments = [];

        // Attach PDF invoice if it exists
        if (!empty($this->invoicePdfPath) && Storage::exists($this->invoicePdfPath)) {
            $customerName = str_replace(' ', '_', $this->getCustomerName());
            $planName = str_replace(' ', '_', $this->getPlanName());
            $date = $this->subscriptionPayment->paid_at->format('Y_m_d');
            
            $attachments[] = Attachment::fromStorage($this->invoicePdfPath)
                ->as("Invoice_{$customerName}_{$planName}_{$date}.pdf")
                ->withMime('application/pdf');
        }

        return $attachments;
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
     * Get plan price from subscription or use payment amount.
     */
    private function getPlanPrice(): float
    {
        if ($this->subscriptionPayment->subscription?->plan) {
            return $this->subscriptionPayment->subscription->plan->price;
        }

        return $this->subscriptionPayment->amount;
    }

    /**
     * Get subscription start date.
     */
    private function getSubscriptionStartDate(): ?\Carbon\Carbon
    {
        if ($this->subscriptionPayment->subscription) {
            return $this->subscriptionPayment->subscription->starts_at;
        }

        // For new subscriptions, start date is payment date
        return $this->subscriptionPayment->paid_at;
    }

    /**
     * Get subscription end date.
     */
    private function getSubscriptionEndDate(): ?\Carbon\Carbon
    {
        if ($this->subscriptionPayment->subscription) {
            return $this->subscriptionPayment->subscription->ends_at;
        }

        // For new subscriptions, calculate end date based on plan
        $startDate = $this->getSubscriptionStartDate();
        if ($startDate) {
            // Default to monthly billing
            return $startDate->copy()->addMonth();
        }

        return null;
    }
}