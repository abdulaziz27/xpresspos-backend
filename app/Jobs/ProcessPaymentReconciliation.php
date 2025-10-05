<?php

namespace App\Jobs;

use App\Services\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected ?string $paymentId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentReconciliationService $reconciliationService): void
    {
        try {
            if ($this->paymentId) {
                // Reconcile specific payment
                $payment = \App\Models\Payment::find($this->paymentId);
                if ($payment) {
                    $reconciliationService->reconcilePayment($payment);
                    Log::info('Payment reconciliation job completed for specific payment', [
                        'payment_id' => $this->paymentId,
                    ]);
                }
            } else {
                // Reconcile all pending payments
                $results = $reconciliationService->reconcileAllPendingPayments();
                Log::info('Payment reconciliation job completed', $results);
            }
        } catch (\Exception $e) {
            Log::error('Payment reconciliation job failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment reconciliation job failed permanently', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
