<?php

namespace App\Jobs;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendUpgradeRecommendationNotification implements ShouldQueue
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
                Log::warning('Attempted to send upgrade recommendation for store without active subscription', [
                    'store_id' => $this->store->id,
                    'store_name' => $this->store->name,
                ]);
                return;
            }
            
            // Get store owner(s)
            $owners = $this->store->users()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'owner');
                })
                ->get();
            
            if ($owners->isEmpty()) {
                Log::warning('No owners found for store, cannot send upgrade recommendation', [
                    'store_id' => $this->store->id,
                    'store_name' => $this->store->name,
                ]);
                return;
            }
            
            $notificationData = $this->prepareNotificationData();
            
            // Send notification to each owner
            foreach ($owners as $owner) {
                $this->sendNotificationToOwner($owner, $notificationData);
            }
            
            Log::info('Upgrade recommendation notification sent successfully', [
                'store_id' => $this->store->id,
                'recipients' => $owners->count(),
                'current_plan' => $subscription->plan->name,
                'recommended_plan' => $notificationData['recommended_plan'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send upgrade recommendation notification', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }
    
    /**
     * Prepare notification data.
     */
    private function prepareNotificationData(): array
    {
        $subscription = $this->store->activeSubscription;
        $currentPlan = $subscription->plan;
        $recommendedPlan = $this->getRecommendedPlan($currentPlan);
        
        // Get usage summary
        $usageSummary = $this->getUsageSummary();
        
        return [
            'type' => 'upgrade_recommendation',
            'store_name' => $this->store->name,
            'current_plan' => [
                'name' => $currentPlan->name,
                'price' => $currentPlan->price,
                'features' => $currentPlan->features,
                'limits' => $currentPlan->limits,
            ],
            'recommended_plan' => $recommendedPlan,
            'usage_summary' => $usageSummary,
            'benefits' => $this->getUpgradeBenefits($currentPlan, $recommendedPlan),
            'upgrade_url' => config('app.url') . '/subscription/upgrade',
            'dashboard_url' => config('app.url') . '/admin/dashboard',
            'contact_url' => config('app.url') . '/contact',
        ];
    }
    
    /**
     * Get recommended plan based on current plan.
     */
    private function getRecommendedPlan($currentPlan): array
    {
        // Simple upgrade path: Basic -> Pro -> Enterprise
        $upgradePath = [
            'basic' => 'pro',
            'pro' => 'enterprise',
        ];
        
        $recommendedSlug = $upgradePath[$currentPlan->slug] ?? 'enterprise';
        
        $recommendedPlan = \App\Models\Plan::where('slug', $recommendedSlug)->first();
        
        if (!$recommendedPlan) {
            // Fallback to Enterprise plan
            $recommendedPlan = \App\Models\Plan::where('slug', 'enterprise')->first();
        }
        
        return [
            'name' => $recommendedPlan->name,
            'slug' => $recommendedPlan->slug,
            'price' => $recommendedPlan->price,
            'annual_price' => $recommendedPlan->annual_price,
            'features' => $recommendedPlan->features,
            'limits' => $recommendedPlan->limits,
            'description' => $recommendedPlan->description,
        ];
    }
    
    /**
     * Get usage summary for the store.
     */
    private function getUsageSummary(): array
    {
        $subscription = $this->store->activeSubscription;
        $plan = $subscription->plan;
        
        $summary = [];
        
        // Check transaction usage
        $transactionUsage = $subscription->usage()->where('feature_type', 'transactions')->first();
        if ($transactionUsage && $transactionUsage->annual_quota) {
            $summary['transactions'] = [
                'current' => $transactionUsage->current_usage,
                'quota' => $transactionUsage->annual_quota,
                'percentage' => $transactionUsage->getUsagePercentage(),
                'exceeded' => $transactionUsage->hasExceededQuota(),
            ];
        }
        
        // Check other features
        $features = ['products', 'users', 'outlets'];
        foreach ($features as $feature) {
            $limit = $plan->getLimit($feature);
            if ($limit) {
                $current = $this->store->getCurrentUsage($feature);
                $summary[$feature] = [
                    'current' => $current,
                    'limit' => $limit,
                    'percentage' => ($current / $limit) * 100,
                    'exceeded' => $current >= $limit,
                ];
            }
        }
        
        return $summary;
    }
    
    /**
     * Get upgrade benefits.
     */
    private function getUpgradeBenefits($currentPlan, array $recommendedPlan): array
    {
        $benefits = [];
        
        // Compare limits
        $currentLimits = $currentPlan->limits ?? [];
        $recommendedLimits = $recommendedPlan['limits'] ?? [];
        
        foreach ($recommendedLimits as $feature => $newLimit) {
            $currentLimit = $currentLimits[$feature] ?? 0;
            
            if ($newLimit === null) {
                $benefits[] = "Unlimited {$feature}";
            } elseif ($newLimit > $currentLimit) {
                $benefits[] = "Increase {$feature} limit from {$currentLimit} to {$newLimit}";
            }
        }
        
        // Compare features
        $currentFeatures = $currentPlan->features ?? [];
        $recommendedFeatures = $recommendedPlan['features'] ?? [];
        
        $newFeatures = array_diff($recommendedFeatures, $currentFeatures);
        foreach ($newFeatures as $feature) {
            $benefits[] = "Access to " . str_replace('_', ' ', $feature);
        }
        
        return $benefits;
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
            Log::info('Upgrade recommendation email would be sent', [
                'recipient' => $owner->email,
                'current_plan' => $data['current_plan']['name'],
                'recommended_plan' => $data['recommended_plan']['name'],
                'benefits_count' => count($data['benefits']),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send upgrade recommendation email', [
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
            Log::info('Upgrade recommendation in-app notification would be sent', [
                'user_id' => $owner->id,
                'current_plan' => $data['current_plan']['name'],
                'recommended_plan' => $data['recommended_plan']['name'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send upgrade recommendation in-app notification', [
                'user_id' => $owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendUpgradeRecommendationNotification job failed permanently', [
            'store_id' => $this->store->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}