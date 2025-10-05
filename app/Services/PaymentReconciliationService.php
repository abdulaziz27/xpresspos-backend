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
    public function __construct(
        protected PaymentService $paymentService,
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

        $pendingPayments = Payment::where('status', 'pending')
            ->where('gateway', 'midtrans')
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
     */
    public function reconcilePayment(Payment $payment): bool
    {
        try {
            // Get payment status from Midtrans
            $status = $this->getPaymentStatusFromMidtrans($payment->gateway_transaction_id);

            if (!$status) {
                Log::warning('Could not retrieve payment status from Midtrans', [
                    'payment_id' => $payment->id,
                    'gateway_transaction_id' => $payment->gateway_transaction_id,
                ]);
                return false;
            }

            // Update payment status if it has changed
            if ($status['transaction_status'] !== $payment->status) {
                $this->updatePaymentFromStatus($payment, $status);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Payment reconciliation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get payment status from Midtrans API.
     */
    private function getPaymentStatusFromMidtrans(string $orderId): ?array
    {
        try {
            // Use Midtrans Core API to get transaction status
            $response = \Midtrans\Transaction::status($orderId);

            if ($response && isset($response['transaction_status'])) {
                return $response;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get payment status from Midtrans', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update payment based on Midtrans status.
     */
    private function updatePaymentFromStatus(Payment $payment, array $status): void
    {
        DB::transaction(function () use ($payment, $status) {
            $newStatus = $this->paymentService->mapMidtransStatus(
                $status['transaction_status'],
                $status['fraud_status'] ?? null
            );

            $payment->update([
                'status' => $newStatus,
                'gateway_response' => array_merge($payment->gateway_response ?? [], $status),
                'processed_at' => $newStatus === 'completed' ? now() : $payment->processed_at,
            ]);

            // Update invoice status
            if ($payment->invoice) {
                if ($newStatus === 'completed') {
                    $this->invoiceService->markInvoiceAsPaid($payment->invoice);
                } elseif (in_array($newStatus, ['failed', 'cancelled'])) {
                    $payment->invoice->markAsFailed();
                }
            }

            Log::info('Payment status updated from reconciliation', [
                'payment_id' => $payment->id,
                'old_status' => $payment->getOriginal('status'),
                'new_status' => $newStatus,
                'gateway_transaction_id' => $payment->gateway_transaction_id,
            ]);
        });
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

        $failedPayments = Payment::where('status', 'failed')
            ->where('gateway', 'midtrans')
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
        $pendingPayments = Payment::where('status', 'pending')
            ->where('gateway', 'midtrans')
            ->count();

        $failedPayments = Payment::where('status', 'failed')
            ->where('gateway', 'midtrans')
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
        $deletedCount = Payment::where('created_at', '<', now()->subDays($daysOld))
            ->where('status', 'failed')
            ->where('gateway', 'midtrans')
            ->delete();

        Log::info('Cleaned up old failed payments', [
            'deleted_count' => $deletedCount,
            'days_old' => $daysOld,
        ]);

        return $deletedCount;
    }
}
