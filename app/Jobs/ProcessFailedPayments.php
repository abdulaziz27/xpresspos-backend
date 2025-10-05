<?php

namespace App\Jobs;

use App\Services\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFailedPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(PaymentReconciliationService $reconciliationService): void
    {
        try {
            $results = $reconciliationService->processFailedPayments();

            Log::info('Failed payments processing job completed', $results);

            // If we created retry invoices, we might want to send notifications
            if ($results['retry_invoices_created'] > 0) {
                Log::info('Retry invoices created, consider sending notifications', [
                    'retry_invoices_created' => $results['retry_invoices_created'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed payments processing job failed', [
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
        Log::error('Failed payments processing job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
