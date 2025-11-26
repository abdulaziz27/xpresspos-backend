<?php

namespace App\Notifications;

use App\Models\AddOnPayment;
use App\Support\Currency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AddOnPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected AddOnPayment $payment
    ) {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenantAddOn = $this->payment->tenantAddOn;
        $addOn = $tenantAddOn?->addOn;
        $expiresAt = $this->payment->expires_at ? $this->payment->expires_at->timezone(config('app.timezone')) : null;

        return (new MailMessage)
            ->subject('Pengingat Pembayaran Add-on XpressPOS')
            ->view('emails.addon-payment-reminder', [
                'user' => $notifiable,
                'payment' => $this->payment,
                'tenantAddOn' => $tenantAddOn,
                'addOn' => $addOn,
                'expiresAt' => $expiresAt,
                'invoiceUrl' => $this->payment->invoice_url,
                'amountFormatted' => Currency::rupiah($this->payment->amount ?? 0),
                'hoursRemaining' => $expiresAt ? now()->diffInHours($expiresAt, false) : null,
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $tenantAddOn = $this->payment->tenantAddOn;
        $addOn = $tenantAddOn?->addOn;

        return [
            'payment_id' => $this->payment->id,
            'tenant_add_on_id' => $tenantAddOn?->id,
            'add_on_name' => $addOn?->name,
            'amount' => $this->payment->amount,
            'status' => $this->payment->status,
            'invoice_url' => $this->payment->invoice_url,
            'expires_at' => optional($this->payment->expires_at)->toDateTimeString(),
            'message' => sprintf(
                'Segera selesaikan pembayaran add-on %s sebelum %s.',
                $addOn?->name ?? 'Add-on',
                optional($this->payment->expires_at)?->format('d M Y H:i')
            ),
        ];
    }
}
