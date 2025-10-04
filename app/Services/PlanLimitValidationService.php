<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Subscription;
use App\Jobs\SendQuotaWarningNotification;
use App\Jobs\SendUpgradeRecommendationNotification;

class PlanLimitValidationService
{
    /**
     * Validate if store can perform an action based on plan limits.
     */
    public function canPerformAction(Store $store, string $feature, int $increment = 1): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'Store has no active subscription',
            ];
        }
        
        if ($subscription->hasExpired()) {
            return [
                'allowed' => false,
                'reason' => 'subscription_expired',
                'message' => 'Subscription has expired',
            ];
        }
        
        $plan = $subscription->plan;
        
        // Check feature access
        if (!$plan->hasFeature($feature)) {
            return [
                'allowed' => false,
                'reason' => 'feature_not_available',
                'message' => "Feature requires {$plan->getRequiredPlanFor($feature)} plan or higher",
                'required_plan' => $plan->getRequiredPlanFor($feature),
            ];
        }
        
        // Check hard limits
        $limit = $plan->getLimit($feature);
        if ($limit !== null) {
            $currentUsage = $store->getCurrentUsage($feature);
            
            if ($currentUsage + $increment > $limit) {
                return [
                    'allowed' => false,
                    'reason' => 'limit_exceeded',
                    'message' => "Would exceed {$feature} limit for your plan",
                    'current_usage' => $currentUsage,
                    'limit' => $limit,
                    'attempted_increment' => $increment,
                ];
            }
        }
        
        // Check transaction quota (soft cap)
        if ($feature === 'transactions') {
            $quotaCheck = $this->checkTransactionQuota($store, $increment);
            if (!$quotaCheck['allowed']) {
                return $quotaCheck;
            }
        }
        
        return [
            'allowed' => true,
            'reason' => 'within_limits',
            'message' => 'Action is allowed',
        ];
    }
    
    /**
     * Increment usage for a feature.
     */
    public function incrementUsage(Store $store, string $feature, int $increment = 1): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return [
                'success' => false,
                'message' => 'No active subscription',
            ];
        }
        
        // For transaction tracking, update the usage record
        if ($feature === 'transactions') {
            return $this->incrementTransactionUsage($subscription, $increment);
        }
        
        // For other features, the usage is calculated dynamically
        return [
            'success' => true,
            'message' => 'Usage tracked (calculated dynamically)',
        ];
    }
    
    /**
     * Check transaction quota with soft cap logic.
     */
    private function checkTransactionQuota(Store $store, int $increment = 1): array
    {
        $subscription = $store->activeSubscription;
        $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
        
        if (!$usage || !$usage->annual_quota) {
            return [
                'allowed' => true,
                'reason' => 'unlimited_transactions',
                'message' => 'Unlimited transactions allowed',
            ];
        }
        
        $newUsage = $usage->current_usage + $increment;
        $usagePercentage = ($newUsage / $usage->annual_quota) * 100;
        
        // Always allow transactions (soft cap), but trigger warnings
        $result = [
            'allowed' => true,
            'current_usage' => $usage->current_usage,
            'annual_quota' => $usage->annual_quota,
            'new_usage' => $newUsage,
            'usage_percentage' => $usagePercentage,
        ];
        
        if ($newUsage >= $usage->annual_quota) {
            $result['reason'] = 'quota_exceeded_soft_cap';
            $result['message'] = 'Transaction quota exceeded but processing continues (soft cap)';
            $result['soft_cap_triggered'] = true;
        } elseif ($usagePercentage >= 80) {
            $result['reason'] = 'approaching_quota_limit';
            $result['message'] = 'Approaching transaction quota limit';
            $result['warning_triggered'] = true;
        } else {
            $result['reason'] = 'within_quota';
            $result['message'] = 'Transaction within quota limits';
        }
        
        return $result;
    }
    
    /**
     * Increment transaction usage and handle notifications.
     */
    private function incrementTransactionUsage(Subscription $subscription, int $increment = 1): array
    {
        $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
        
        if (!$usage) {
            return [
                'success' => false,
                'message' => 'Transaction usage tracking not initialized',
            ];
        }
        
        $oldUsage = $usage->current_usage;
        $oldPercentage = $usage->getUsagePercentage();
        
        // Increment usage
        $usage->incrementUsage($increment);
        
        $newUsage = $usage->current_usage;
        $newPercentage = $usage->getUsagePercentage();
        
        // Check if we need to trigger notifications
        $this->checkAndTriggerNotifications($subscription->store, $usage, $oldPercentage, $newPercentage);
        
        return [
            'success' => true,
            'message' => 'Transaction usage incremented',
            'old_usage' => $oldUsage,
            'new_usage' => $newUsage,
            'usage_percentage' => $newPercentage,
            'quota_exceeded' => $usage->hasExceededQuota(),
            'soft_cap_triggered' => $usage->soft_cap_triggered,
        ];
    }
    
    /**
     * Check and trigger notifications based on usage thresholds.
     */
    private function checkAndTriggerNotifications(Store $store, $usage, float $oldPercentage, float $newPercentage): void
    {
        // Trigger soft cap warning at 80%
        if ($oldPercentage < 80 && $newPercentage >= 80) {
            dispatch(new SendQuotaWarningNotification($store));
        }
        
        // Trigger quota exceeded notification at 100%
        if ($oldPercentage < 100 && $newPercentage >= 100) {
            dispatch(new SendUpgradeRecommendationNotification($store));
        }
        
        // Trigger additional warnings at 90% and 95%
        if ($oldPercentage < 90 && $newPercentage >= 90) {
            dispatch(new SendQuotaWarningNotification($store));
        }
        
        if ($oldPercentage < 95 && $newPercentage >= 95) {
            dispatch(new SendQuotaWarningNotification($store));
        }
    }
    
    /**
     * Get usage summary for a store.
     */
    public function getUsageSummary(Store $store): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return [
                'status' => 'no_subscription',
                'message' => 'Store has no active subscription',
            ];
        }
        
        $plan = $subscription->plan;
        $summary = [
            'subscription_status' => $subscription->status,
            'plan_name' => $plan->name,
            'features' => [],
        ];
        
        // Check each feature
        $features = ['products', 'users', 'outlets', 'transactions'];
        
        foreach ($features as $feature) {
            $limit = $plan->getLimit($feature);
            $currentUsage = $store->getCurrentUsage($feature);
            
            $featureSummary = [
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'unlimited' => $limit === null,
                'usage_percentage' => $limit ? ($currentUsage / $limit) * 100 : 0,
                'status' => 'within_limits',
            ];
            
            if ($limit && $currentUsage >= $limit) {
                $featureSummary['status'] = 'limit_exceeded';
            } elseif ($limit && ($currentUsage / $limit) >= 0.8) {
                $featureSummary['status'] = 'approaching_limit';
            }
            
            // Special handling for transactions (soft cap)
            if ($feature === 'transactions') {
                $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
                if ($usage) {
                    $featureSummary['annual_quota'] = $usage->annual_quota;
                    $featureSummary['soft_cap_triggered'] = $usage->soft_cap_triggered;
                    $featureSummary['quota_exceeded'] = $usage->hasExceededQuota();
                }
            }
            
            $summary['features'][$feature] = $featureSummary;
        }
        
        return $summary;
    }
    
    /**
     * Reset usage for new subscription year.
     */
    public function resetAnnualUsage(Subscription $subscription): array
    {
        $resetCount = 0;
        $errors = [];
        
        foreach ($subscription->usage as $usage) {
            try {
                if ($usage->feature_type === 'transactions') {
                    $usage->resetForNewYear();
                    $resetCount++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'feature' => $usage->feature_type,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'success' => empty($errors),
            'reset_count' => $resetCount,
            'errors' => $errors,
        ];
    }
    
    /**
     * Get stores that need attention (approaching limits or exceeded).
     */
    public function getStoresNeedingAttention(): array
    {
        $stores = Store::whereHas('activeSubscription')->with(['activeSubscription.plan', 'activeSubscription.usage'])->get();
        
        $needingAttention = [];
        
        foreach ($stores as $store) {
            $issues = [];
            $subscription = $store->activeSubscription;
            
            if (!$subscription) {
                continue;
            }
            
            $plan = $subscription->plan;
            
            // Check each feature for issues
            $features = ['products', 'users', 'outlets'];
            
            foreach ($features as $feature) {
                $limit = $plan->getLimit($feature);
                if (!$limit) continue;
                
                $currentUsage = $store->getCurrentUsage($feature);
                $usagePercentage = ($currentUsage / $limit) * 100;
                
                if ($currentUsage >= $limit) {
                    $issues[] = [
                        'type' => 'limit_exceeded',
                        'feature' => $feature,
                        'usage' => $currentUsage,
                        'limit' => $limit,
                        'percentage' => $usagePercentage,
                    ];
                } elseif ($usagePercentage >= 80) {
                    $issues[] = [
                        'type' => 'approaching_limit',
                        'feature' => $feature,
                        'usage' => $currentUsage,
                        'limit' => $limit,
                        'percentage' => $usagePercentage,
                    ];
                }
            }
            
            // Check transaction quota
            $transactionUsage = $subscription->usage()->where('feature_type', 'transactions')->first();
            if ($transactionUsage && $transactionUsage->annual_quota) {
                $percentage = $transactionUsage->getUsagePercentage();
                
                if ($transactionUsage->hasExceededQuota()) {
                    $issues[] = [
                        'type' => 'quota_exceeded',
                        'feature' => 'transactions',
                        'usage' => $transactionUsage->current_usage,
                        'quota' => $transactionUsage->annual_quota,
                        'percentage' => $percentage,
                    ];
                } elseif ($percentage >= 80) {
                    $issues[] = [
                        'type' => 'approaching_quota',
                        'feature' => 'transactions',
                        'usage' => $transactionUsage->current_usage,
                        'quota' => $transactionUsage->annual_quota,
                        'percentage' => $percentage,
                    ];
                }
            }
            
            if (!empty($issues)) {
                $needingAttention[] = [
                    'store' => $store,
                    'subscription' => $subscription,
                    'issues' => $issues,
                ];
            }
        }
        
        return $needingAttention;
    }
}