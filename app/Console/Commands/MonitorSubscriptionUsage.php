<?php

namespace App\Console\Commands;

use App\Jobs\SendQuotaWarningNotification;
use App\Jobs\SendUpgradeRecommendationNotification;
use App\Services\PlanLimitValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSubscriptionUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:monitor-usage 
                            {--dry-run : Show what notifications would be sent without actually sending them}
                            {--force : Force send notifications even if recently sent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor subscription usage and send alerts for stores approaching or exceeding limits';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info('Starting subscription usage monitoring...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No notifications will be sent');
        }
        
        try {
            $planLimitService = app(PlanLimitValidationService::class);
            $storesNeedingAttention = $planLimitService->getStoresNeedingAttention();
            
            if (empty($storesNeedingAttention)) {
                $this->info('No stores found that need attention.');
                return 0;
            }
            
            $this->info("Found " . count($storesNeedingAttention) . " store(s) that need attention:");
            
            $warningsSent = 0;
            $upgradeRecommendationsSent = 0;
            $errors = [];
            
            foreach ($storesNeedingAttention as $storeData) {
                $store = $storeData['store'];
                $subscription = $storeData['subscription'];
                $issues = $storeData['issues'];
                
                $this->line("\nStore: {$store->name} (Plan: {$subscription->plan->name})");
                
                $hasQuotaExceeded = false;
                $hasApproachingLimits = false;
                
                foreach ($issues as $issue) {
                    $limit = $issue['limit'] ?? $issue['quota'] ?? 'unlimited';
                    $this->line("  - {$issue['type']}: {$issue['feature']} ({$issue['usage']}/{$limit}) - {$issue['percentage']}%");
                    
                    if (in_array($issue['type'], ['quota_exceeded', 'limit_exceeded'])) {
                        $hasQuotaExceeded = true;
                    } elseif (in_array($issue['type'], ['approaching_quota', 'approaching_limit'])) {
                        $hasApproachingLimits = true;
                    }
                }
                
                try {
                    // Send upgrade recommendation for exceeded limits/quotas
                    if ($hasQuotaExceeded) {
                        if ($this->shouldSendUpgradeRecommendation($store, $force)) {
                            if (!$dryRun) {
                                dispatch(new SendUpgradeRecommendationNotification($store));
                                $upgradeRecommendationsSent++;
                                $this->info("  ✓ Upgrade recommendation notification queued");
                            } else {
                                $this->info("  → Would send upgrade recommendation notification");
                            }
                        } else {
                            $this->line("  - Upgrade recommendation recently sent, skipping");
                        }
                    }
                    
                    // Send warning for approaching limits
                    if ($hasApproachingLimits) {
                        if ($this->shouldSendQuotaWarning($store, $force)) {
                            if (!$dryRun) {
                                dispatch(new SendQuotaWarningNotification($store));
                                $warningsSent++;
                                $this->info("  ✓ Quota warning notification queued");
                            } else {
                                $this->info("  → Would send quota warning notification");
                            }
                        } else {
                            $this->line("  - Quota warning recently sent, skipping");
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errorMsg = "Failed to queue notifications for {$store->name}: {$e->getMessage()}";
                    $this->error("  ✗ {$errorMsg}");
                    $errors[] = $errorMsg;
                    
                    Log::error('Error queuing usage monitoring notifications', [
                        'store_id' => $store->id,
                        'store_name' => $store->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            if (!$dryRun) {
                $this->info("\nUsage monitoring completed:");
                $this->info("- Stores monitored: " . count($storesNeedingAttention));
                $this->info("- Quota warnings sent: {$warningsSent}");
                $this->info("- Upgrade recommendations sent: {$upgradeRecommendationsSent}");
                
                if (!empty($errors)) {
                    $this->error("- Errors encountered: " . count($errors));
                    foreach ($errors as $error) {
                        $this->error("  {$error}");
                    }
                }
                
                Log::info('Subscription usage monitoring completed', [
                    'stores_monitored' => count($storesNeedingAttention),
                    'warnings_sent' => $warningsSent,
                    'upgrade_recommendations_sent' => $upgradeRecommendationsSent,
                    'errors_count' => count($errors),
                ]);
            }
            
            return empty($errors) ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error("Fatal error during usage monitoring: {$e->getMessage()}");
            
            Log::error('Fatal error in usage monitoring command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
    }
    
    /**
     * Check if upgrade recommendation should be sent.
     */
    private function shouldSendUpgradeRecommendation($store, bool $force): bool
    {
        if ($force) {
            return true;
        }
        
        // Check if upgrade recommendation was sent recently (within 7 days)
        $recentNotification = Log::getMonolog()->getHandlers();
        // For now, always return true - in production, this would check a notifications table
        return true;
    }
    
    /**
     * Check if quota warning should be sent.
     */
    private function shouldSendQuotaWarning($store, bool $force): bool
    {
        if ($force) {
            return true;
        }
        
        $subscription = $store->activeSubscription;
        if (!$subscription) {
            return false;
        }
        
        // Check if soft cap was triggered recently
        $transactionUsage = $subscription->usage()->where('feature_type', 'transactions')->first();
        if ($transactionUsage && $transactionUsage->soft_cap_triggered) {
            // Don't send if soft cap was triggered within last 24 hours
            if ($transactionUsage->soft_cap_triggered_at && 
                $transactionUsage->soft_cap_triggered_at->diffInHours(now()) < 24) {
                return false;
            }
        }
        
        return true;
    }
}
