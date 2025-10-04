<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendQuotaWarningNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Store $store
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $subscription = $this->store->activeSubscription;
            
            if (!$subscription) {
                Log::warning('Attempted to send quota warning for store without active subscription', [
                    'store_id' => $this->store->id,
                    'store_name' => $this->store->name,
                ]);
                return;
            }
            
            $transactionUsage = $subscription->usage()
                ->where('feature_type', 'transactions')
                ->first();
            
            if (!$transactionUsage || !$transactionUsage->annual_quota) {
                Log::info('Store has unlimited transactions, skipping quota warning', [
                    'store_id' => $this->store->id,
                    'plan' => $subscription->plan->name,
                ]);
                return;
            }
            
            // Check if we should send the notification
            if (!$this->shouldSendNotification($transactionUsage)) {
                return;
            }
            
            // Get store owner(s)
            $owners = $this->store->users()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'owner');
                })
                ->get();
            
            if ($owners->isEmpty()) {
                Log::warning('No owners found for store, cannot send quota warning', [
                    'store_id' => $this->store->id,
                    'store_name' => $this->store->name,
                ]);
                return;
            }
            
            $notificationData = $this->prepareNotificationData($transactionUsage);
            
            // Send notification to each owner
            foreach ($owners as $owner) {
                $this->sendNotificationToOwner($owner, $notificationData);
            }
            
            // Update the usage record to prevent duplicate notifications
            $this->updateNotificationSent($transactionUsage);
            
            Log::info('Quota warning notification sent successfully', [
                'store_id' => $this->store->id,
                'recipients' => $owners->count(),
                'usage_percentage' => $transactionUsage->getUsagePercentage(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send quota warning notification', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }
    
    /**
     * Determine if notification should be sent.
     */
    private function shouldSendNotification($transactionUsage): bool
    {
        // Don't send if already exceeded (different notification type)
        if ($transactionUsage->hasExceededQuota()) {
            return false;
        }
        
        // Don't send if usage is below 80%
        if ($transactionUsage->getUsagePercentage() < 80) {
            return false;
        }
        
        // Don't send if soft cap notification was already sent recently (within 7 days)
        if ($transactionUsage->soft_cap_triggered && 
            $transactionUsage->soft_cap_triggered_at && 
            $transactionUsage->soft_cap_triggered_at->diffInDays(now()) < 7) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prepare notification data.
     */
    private function prepareNotificationData($transactionUsage): array
    {
        $subscription = $this->store->activeSubscription;
        $usagePercentage = $transactionUsage->getUsagePercentage();
        $remainingTransactions = $transactionUsage->annual_quota - $transactionUsage->current_usage;
        
        return [
            'type' => $transactionUsage->hasExceededQuota() ? 'quota_exceeded' : 'quota_warning',
            'store_name' => $this->store->name,
            'plan_name' => $subscription->plan->name,
            'current_usage' => $transactionUsage->current_usage,
            'annual_quota' => $transactionUsage->annual_quota,
            'usage_percentage' => round($usagePercentage, 1),
            'remaining_transactions' => max(0, $remainingTransactions),
            'subscription_year_end' => $transactionUsage->subscription_year_end,
            'upgrade_url' => config('app.url') . '/subscription/upgrade',
            'dashboard_url' => config('app.url') . '/admin/dashboard',
        ];
    }
    
    /**
     * Send notification to store owner.
     */
    private function sendNotificationToOwner($owner, array $data): void
    {
        // Send email notification
        $this->sendEmailNotification($owner, $data);
        
        // Send in-app notification (if implemented)
        $this->sendInAppNotification($owner, $data);
    }
    
    /**
     * Send email notification.
     */
    private function sendEmailNotification($owner, array $data): void
    {
        try {
            // TODO: Implement email notification using Laravel Mail
            // For now, just log the notification
            Log::info('Email notification would be sent', [
                'recipient' => $owner->email,
                'type' => $data['type'],
                'usage_percentage' => $data['usage_percentage'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'recipient' => $owner->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Send in-app notification.
     */
    private function sendInAppNotification($owner, array $data): void
    {
        try {
            // TODO: Implement in-app notification system
            // For now, just log the notification
            Log::info('In-app notification would be sent', [
                'user_id' => $owner->id,
                'type' => $data['type'],
                'usage_percentage' => $data['usage_percentage'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send in-app notification', [
                'user_id' => $owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Update notification sent status.
     */
    private function updateNotificationSent($transactionUsage): void
    {
        if (!$transactionUsage->soft_cap_triggered) {
            $transactionUsage->triggerSoftCap();
        }
    }
    
    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendQuotaWarningNotification job failed permanently', [
            'store_id' => $this->store->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}