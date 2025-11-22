<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    // NOTE: PaymentService (Midtrans) telah dihapus karena tidak digunakan.
    
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Reconcile all pending payments.
     */
    public function reconcileAllPendingPayments(): array
    {
        $results = [
            'processed' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        // NOTE: Midtrans gateway telah dihapus. Skip payment dengan gateway midtrans.
        $pendingPayments = Payment::where('status', 'pending')
            ->where('gateway', '!=', 'midtrans') // Skip Midtrans payments
            ->where('created_at', '>=', now()->subDays(7)) // Only check payments from last 7 days
            ->get();

        foreach ($pendingPayments as $payment) {
            try {
                $results['processed']++;

                if ($this->reconcilePayment($payment)) {
                    $results['updated']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Payment reconciliation failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Reconcile a specific payment.
     * 
     * NOTE: Midtrans reconciliation telah dihapus.
     * Perlu di-refactor untuk Xendit jika diperlukan.
     */
    public function reconcilePayment(Payment $payment): bool
    {
        // Skip Midtrans payments
        if ($payment->gateway === 'midtrans') {
            Log::info('Skipping Midtrans payment reconciliation (Midtrans removed)', [
                'payment_id' => $payment->id,
            ]);
            return false;
        }

        // TODO: Implement reconciliation for other gateways (Xendit, etc.)
        Log::info('Payment reconciliation not implemented for gateway', [
                'payment_id' => $payment->id,
            'gateway' => $payment->gateway,
            ]);
        
        return false;
    }

    /**
     * Check for failed payments and create retry invoices.
     */
    public function processFailedPayments(): array
    {
        $results = [
            'processed' => 0,
            'retry_invoices_created' => 0,
            'errors' => [],
        ];

        // NOTE: Midtrans gateway telah dihapus. Skip payment dengan gateway midtrans.
        $failedPayments = Payment::where('status', 'failed')
            ->where('gateway', '!=', 'midtrans') // Skip Midtrans payments
            ->where('created_at', '>=', now()->subDays(30)) // Last 30 days
            ->with('invoice.subscription')
            ->get();

        foreach ($failedPayments as $payment) {
            try {
                $results['processed']++;

                if ($this->shouldCreateRetryInvoice($payment)) {
                    $this->createRetryInvoice($payment);
                    $results['retry_invoices_created']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Check if a retry invoice should be created for failed payment.
     */
    private function shouldCreateRetryInvoice(Payment $payment): bool
    {
        if (!$payment->invoice || !$payment->invoice->subscription) {
            return false;
        }

        $invoice = $payment->invoice;
        $subscription = $invoice->subscription;

        // Don't create retry if subscription is cancelled or expired
        if (in_array($subscription->status, ['cancelled', 'expired'])) {
            return false;
        }

        // Don't create retry if invoice is already paid
        if ($invoice->isPaid()) {
            return false;
        }

        // Don't create retry if payment failed more than 3 times
        $retryCount = $subscription->invoices()
            ->where('metadata->type', 'retry')
            ->where('created_at', '>=', $payment->created_at)
            ->count();

        return $retryCount < 3;
    }

    /**
     * Create retry invoice for failed payment.
     */
    private function createRetryInvoice(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $subscription = $invoice->subscription;

        $retryInvoice = $this->invoiceService->createInvoice($subscription, [
            'amount' => $invoice->amount,
            'tax_amount' => $invoice->tax_amount,
            'due_date' => now()->addDays(3), // Give 3 days to pay
            'metadata' => [
                'type' => 'retry',
                'original_invoice_id' => $invoice->id,
                'failed_payment_id' => $payment->id,
                'retry_created_at' => now(),
            ],
        ]);

        Log::info('Retry invoice created for failed payment', [
            'original_invoice_id' => $invoice->id,
            'retry_invoice_id' => $retryInvoice->id,
            'failed_payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Get payment reconciliation summary.
     */
    public function getReconciliationSummary(): array
    {
        // NOTE: Midtrans gateway telah dihapus. Hanya hitung payment non-Midtrans.
        $pendingPayments = Payment::where('status', 'pending')
            ->where('gateway', '!=', 'midtrans')
            ->count();

        $failedPayments = Payment::where('status', 'failed')
            ->where('gateway', '!=', 'midtrans')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $overdueInvoices = Invoice::where('status', 'pending')
            ->where('due_date', '<', now())
            ->count();

        return [
            'pending_payments' => $pendingPayments,
            'failed_payments_7_days' => $failedPayments,
            'overdue_invoices' => $overdueInvoices,
            'last_reconciliation' => $this->getLastReconciliationTime(),
        ];
    }

    /**
     * Get last reconciliation time from logs.
     */
    private function getLastReconciliationTime(): ?Carbon
    {
        // This would typically be stored in a database table or cache
        // For now, we'll return null
        return null;
    }

    /**
     * Clean up old payment records.
     */
    public function cleanupOldPayments(int $daysOld = 90): int
    {
        // NOTE: Midtrans gateway telah dihapus. Hanya hapus payment non-Midtrans.
        $deletedCount = Payment::where('created_at', '<', now()->subDays($daysOld))
            ->where('status', 'failed')
            ->where('gateway', '!=', 'midtrans')
            ->delete();

        Log::info('Cleaned up old failed payments', [
            'deleted_count' => $deletedCount,
            'days_old' => $daysOld,
        ]);

        return $deletedCount;
    }
}
