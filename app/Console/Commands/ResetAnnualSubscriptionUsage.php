<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\PlanLimitValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetAnnualSubscriptionUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:reset-annual-usage 
                            {--dry-run : Show what would be reset without actually doing it}
                            {--store= : Reset usage for specific store ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset annual subscription usage counters for subscriptions that have reached their renewal date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $storeId = $this->option('store');
        
        $this->info('Starting annual subscription usage reset...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        try {
            $subscriptionsQuery = Subscription::active()
                ->where('billing_cycle', 'annual')
                ->whereHas('usage', function ($query) {
                    $query->where('subscription_year_end', '<=', now());
                });
            
            if ($storeId) {
                $subscriptionsQuery->where('store_id', $storeId);
                $this->info("Filtering for store ID: {$storeId}");
            }
            
            $subscriptions = $subscriptionsQuery->with(['store', 'plan', 'usage'])->get();
            
            if ($subscriptions->isEmpty()) {
                $this->info('No subscriptions found that need annual usage reset.');
                return 0;
            }
            
            $this->info("Found {$subscriptions->count()} subscription(s) that need annual usage reset:");
            
            $planLimitService = app(PlanLimitValidationService::class);
            $totalReset = 0;
            $errors = [];
            
            foreach ($subscriptions as $subscription) {
                $store = $subscription->store;
                $plan = $subscription->plan;
                
                $this->line("Processing: {$store->name} (Plan: {$plan->name})");
                
                if ($dryRun) {
                    // Show what would be reset
                    foreach ($subscription->usage as $usage) {
                        if ($usage->feature_type === 'transactions') {
                            $this->line("  - Would reset {$usage->feature_type}: {$usage->current_usage} -> 0");
                        }
                    }
                    continue;
                }
                
                try {
                    $result = $planLimitService->resetAnnualUsage($subscription);
                    
                    if ($result['success']) {
                        $totalReset += $result['reset_count'];
                        $this->info("  ✓ Reset {$result['reset_count']} usage counter(s)");
                        
                        // Update subscription year for usage records
                        foreach ($subscription->usage as $usage) {
                            if ($usage->feature_type === 'transactions') {
                                $usage->update([
                                    'subscription_year_start' => now()->startOfYear(),
                                    'subscription_year_end' => now()->endOfYear(),
                                ]);
                            }
                        }
                        
                        Log::info('Annual usage reset completed for subscription', [
                            'subscription_id' => $subscription->id,
                            'store_id' => $store->id,
                            'store_name' => $store->name,
                            'reset_count' => $result['reset_count'],
                        ]);
                        
                    } else {
                        $errorMsg = "Failed to reset usage for {$store->name}";
                        $this->error("  ✗ {$errorMsg}");
                        $errors[] = $errorMsg;
                        
                        foreach ($result['errors'] as $error) {
                            $this->error("    - {$error['feature']}: {$error['error']}");
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errorMsg = "Exception resetting usage for {$store->name}: {$e->getMessage()}";
                    $this->error("  ✗ {$errorMsg}");
                    $errors[] = $errorMsg;
                    
                    Log::error('Error during annual usage reset', [
                        'subscription_id' => $subscription->id,
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
            
            if (!$dryRun) {
                $this->info("\nAnnual usage reset completed:");
                $this->info("- Total usage counters reset: {$totalReset}");
                $this->info("- Subscriptions processed: {$subscriptions->count()}");
                
                if (!empty($errors)) {
                    $this->error("- Errors encountered: " . count($errors));
                    foreach ($errors as $error) {
                        $this->error("  {$error}");
                    }
                }
                
                Log::info('Annual subscription usage reset command completed', [
                    'total_subscriptions' => $subscriptions->count(),
                    'total_reset' => $totalReset,
                    'errors_count' => count($errors),
                ]);
            }
            
            return empty($errors) ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error("Fatal error during annual usage reset: {$e->getMessage()}");
            
            Log::error('Fatal error in annual usage reset command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
    }
}
