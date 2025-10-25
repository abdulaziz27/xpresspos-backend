<?php

namespace App\Console\Commands;

use App\Services\SubscriptionPaymentNotificationService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscription:send-payment-reminders 
                            {--days=7 : Number of days before expiration to send reminder}
                            {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send payment reminder emails for subscriptions expiring soon';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionPaymentNotificationService $notificationService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Sending payment reminders for subscriptions expiring in {$days} days...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No emails will be sent');
            
            // Get subscriptions that would receive reminders
            $subscriptions = \App\Models\Subscription::active()
                ->expiringSoon($days)
                ->with(['store', 'plan'])
                ->get();

            $this->table(
                ['Store Name', 'Plan', 'Expires At', 'Email'],
                $subscriptions->map(function ($subscription) {
                    return [
                        $subscription->store->name,
                        $subscription->plan->name,
                        $subscription->ends_at->format('M j, Y'),
                        $subscription->store->email ?? 'No email',
                    ];
                })->toArray()
            );

            $this->info("Would send {$subscriptions->count()} reminder emails");
            return self::SUCCESS;
        }

        $sentCount = $notificationService->sendBulkPaymentReminders($days);

        $this->info("Successfully sent {$sentCount} payment reminder emails");

        return self::SUCCESS;
    }
}