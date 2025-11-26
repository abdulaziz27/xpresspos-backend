<?php

namespace App\Jobs;

use App\Models\AddOnPayment;
use App\Notifications\AddOnPaymentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AddOnPaymentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
    * Execute the job.
    */
    public function handle(): void
    {
        $now = now();
        $thresholdHours = (int) config('xendit.addon.reminder_hours', 48);
        $reminderCooldownHours = (int) config('xendit.addon.reminder_cooldown_hours', 12);
        $deadline = $now->copy()->addHours($thresholdHours);
        $cooldownCutoff = $now->copy()->subHours($reminderCooldownHours);

        $payments = AddOnPayment::with(['tenantAddOn.tenant'])
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $deadline])
            ->where(function ($query) use ($cooldownCutoff) {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<=', $cooldownCutoff);
            })
            ->get();

        foreach ($payments as $payment) {
            $tenant = $payment->tenantAddOn?->tenant;

            if (! $tenant) {
                Log::channel('payment')->warning('[AddOnPaymentReminderJob] Tenant tidak ditemukan untuk payment', [
                    'payment_id' => $payment->id,
                ]);
                continue;
            }

            $owners = $tenant->users()
                ->whereHas('roles', fn ($query) => $query->where('name', 'owner'))
                ->get();

            if ($owners->isEmpty()) {
                Log::channel('payment')->warning('[AddOnPaymentReminderJob] Tidak ada owner terkait tenant', [
                    'tenant_id' => $tenant->id,
                    'payment_id' => $payment->id,
                ]);
                continue;
            }

            Notification::send($owners, new AddOnPaymentReminderNotification($payment));

            $payment->forceFill([
                'last_reminder_sent_at' => $now,
                'reminder_count' => ($payment->reminder_count ?? 0) + 1,
            ])->save();

            Log::channel('payment')->info('[AddOnPaymentReminderJob] Reminder dikirim', [
                'payment_id' => $payment->id,
                'tenant_id' => $tenant->id,
                'owners' => $owners->pluck('email')->all(),
                'expires_at' => optional($payment->expires_at)->toDateTimeString(),
                'invoice_url' => $payment->invoice_url,
                'reminder_count' => $payment->reminder_count,
            ]);
        }
    }
}
