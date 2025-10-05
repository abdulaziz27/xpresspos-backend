<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPaymentReconciliation;
use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile
                            {--payment-id= : Reconcile specific payment by ID}
                            {--failed : Process failed payments and create retry invoices}
                            {--cleanup : Clean up old failed payments}
                            {--summary : Show reconciliation summary}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile payment statuses with Midtrans and process failed payments';

    /**
     * Execute the console command.
     */
    public function handle(PaymentReconciliationService $reconciliationService): int
    {
        if ($this->option('summary')) {
            return $this->showSummary($reconciliationService);
        }

        if ($this->option('cleanup')) {
            return $this->cleanupOldPayments($reconciliationService);
        }

        if ($this->option('failed')) {
            return $this->processFailedPayments($reconciliationService);
        }

        $paymentId = $this->option('payment-id');

        if ($paymentId) {
            $this->info("Reconciling payment: {$paymentId}");
            ProcessPaymentReconciliation::dispatch($paymentId);
            $this->info('Payment reconciliation job dispatched.');
        } else {
            $this->info('Reconciling all pending payments...');
            ProcessPaymentReconciliation::dispatch();
            $this->info('Payment reconciliation job dispatched.');
        }

        return Command::SUCCESS;
    }

    /**
     * Show reconciliation summary.
     */
    private function showSummary(PaymentReconciliationService $reconciliationService): int
    {
        $summary = $reconciliationService->getReconciliationSummary();

        $this->info('Payment Reconciliation Summary');
        $this->line('============================');
        $this->line("Pending Payments: {$summary['pending_payments']}");
        $this->line("Failed Payments (7 days): {$summary['failed_payments_7_days']}");
        $this->line("Overdue Invoices: {$summary['overdue_invoices']}");

        if ($summary['last_reconciliation']) {
            $this->line("Last Reconciliation: {$summary['last_reconciliation']}");
        } else {
            $this->line('Last Reconciliation: Never');
        }

        return Command::SUCCESS;
    }

    /**
     * Process failed payments.
     */
    private function processFailedPayments(PaymentReconciliationService $reconciliationService): int
    {
        $this->info('Processing failed payments...');

        $results = $reconciliationService->processFailedPayments();

        $this->info("Processed: {$results['processed']} failed payments");
        $this->info("Retry invoices created: {$results['retry_invoices_created']}");

        if (!empty($results['errors'])) {
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->error("- Payment {$error['payment_id']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Clean up old payments.
     */
    private function cleanupOldPayments(PaymentReconciliationService $reconciliationService): int
    {
        $this->info('Cleaning up old failed payments...');

        $deletedCount = $reconciliationService->cleanupOldPayments();

        $this->info("Deleted {$deletedCount} old failed payments");

        return Command::SUCCESS;
    }
}
