<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPayment;
use App\Services\SubscriptionPaymentNotificationService;
use Illuminate\Console\Command;

class ProcessFailedPaymentNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:process-failed-payments 
                            {--retry : Process retry notifications for failed payments}
                            {--suspend : Process suspension notifications for repeatedly failed payments}
                            {--dry-run : Show what would be processed without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Process failed payment notifications and handle retry/suspension logic';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionPaymentNotificationService $notificationService): int
    {
        $retry = $this->option('retry');
        $suspend = $this->option('suspend');
        $dryRun = $this->option('dry-run');

        if ($retry) {
            return $this->processRetryNotifications($notificationService, $dryRun);
        }

        if ($suspend) {
            return $this->processSuspensionNotifications($notificationService, $dryRun);
        }

        $this->error('Please specify either --retry or --suspend option');
        return self::FAILURE;
    }

    /**
     * Process retry notifications for failed payments.
     */
    private function processRetryNotifications(
        SubscriptionPaymentNotificationService $notificationService,
        bool $dryRun
    ): int {
        $this->info('Processing retry notifications for failed payments...');

        // Get failed payments that need retry notifications
        $failedPayments = SubscriptionPayment::failed()
            ->where('created_at', '>=', now()->subDays(3)) // Only recent failures
            ->whereDoesntHave('subscription.store', function ($query) {
                $query->where('status', 'suspended'); // Don't retry for already suspended
            })
            ->with(['landingSubscription', 'subscription.store'])
            ->get();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
            
            $this->table(
                ['Payment ID', 'Customer', 'Amount', 'Failed At', 'Retry Count'],
                $failedPayments->map(function ($payment) {
                    $customerName = $payment->landingSubscription?->name 
                        ?? $payment->subscription?->store?->name 
                        ?? 'Unknown';
                    
                    return [
                        $payment->id,
                        $customerName,
                        'Rp ' . number_format($payment->amount, 0, ',', '.'),
                        $payment->updated_at->format('M j, Y g:i A'),
                        $this->getRetryCount($payment),
                    ];
                })->toArray()
            );

            $this->info("Would process {$failedPayments->count()} failed payments");
            return self::SUCCESS;
        }

        $processedCount = 0;

        foreach ($failedPayments as $payment) {
            $retryCount = $this->getRetryCount($payment);
            
            if ($retryCount < 3) { // Max 3 retries
                $success = $notificationService->sendPaymentFailure(
                    $payment,
                    'Payment processing failed',
                    true,
                    $retryCount
                );

                if ($success) {
                    $processedCount++;
                    $this->info("Sent retry notification for payment {$payment->id} (retry #{$retryCount})");
                }
            }
        }

        $this->info("Successfully processed {$processedCount} retry notifications");
        return self::SUCCESS;
    }

    /**
     * Process suspension notifications for repeatedly failed payments.
     */
    private function processSuspensionNotifications(
        SubscriptionPaymentNotificationService $notificationService,
        bool $dryRun
    ): int {
        $this->info('Processing suspension notifications for repeatedly failed payments...');

        // Get subscriptions with multiple failed payments that should be suspended
        $subscriptionsToSuspend = \App\Models\Subscription::active()
            ->whereHas('subscriptionPayments', function ($query) {
                $query->failed()
                    ->where('created_at', '>=', now()->subDays(7)) // Recent failures
                    ->havingRaw('COUNT(*) >= 3'); // 3 or more failures
            })
            ->with(['store', 'plan', 'subscriptionPayments' => function ($query) {
                $query->failed()->latest();
            }])
            ->get();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No suspensions will be processed');
            
            $this->table(
                ['Subscription ID', 'Store Name', 'Plan', 'Failed Payments', 'Last Failure'],
                $subscriptionsToSuspend->map(function ($subscription) {
                    $failedPayments = $subscription->subscriptionPayments->where('status', 'failed');
                    $lastFailure = $failedPayments->first();
                    
                    return [
                        $subscription->id,
                        $subscription->store->name,
                        $subscription->plan->name,
                        $failedPayments->count(),
                        $lastFailure?->updated_at->format('M j, Y g:i A') ?? 'N/A',
                    ];
                })->toArray()
            );

            $this->info("Would suspend {$subscriptionsToSuspend->count()} subscriptions");
            return self::SUCCESS;
        }

        $suspendedCount = 0;

        foreach ($subscriptionsToSuspend as $subscription) {
            $lastFailedPayment = $subscription->subscriptionPayments
                ->where('status', 'failed')
                ->first();

            // Suspend the subscription
            $subscription->update(['status' => 'suspended']);

            // Send suspension notification
            $success = $notificationService->sendSubscriptionSuspended(
                $subscription,
                $lastFailedPayment,
                'Multiple payment failures after retry attempts',
                7 // 7-day grace period
            );

            if ($success) {
                $suspendedCount++;
                $this->info("Suspended subscription {$subscription->id} and sent notification");
            }
        }

        $this->info("Successfully suspended {$suspendedCount} subscriptions");
        return self::SUCCESS;
    }

    /**
     * Get retry count for a payment (simulate based on creation time).
     */
    private function getRetryCount(SubscriptionPayment $payment): int
    {
        // In a real implementation, this would be stored in the payment record
        // For now, simulate based on how long ago the payment failed
        $hoursAgo = $payment->updated_at->diffInHours(now());
        
        if ($hoursAgo >= 72) return 3; // 3+ days = 3 retries
        if ($hoursAgo >= 48) return 2; // 2+ days = 2 retries
        if ($hoursAgo >= 24) return 1; // 1+ day = 1 retry
        
        return 0; // Recent failure = no retries yet
    }
}