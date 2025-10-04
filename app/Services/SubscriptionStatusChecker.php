<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionStatusChecker
{
    /**
     * Check and update all subscription statuses.
     */
    public function checkAllSubscriptions(): array
    {
        $results = [
            'checked' => 0,
            'expired' => 0,
            'expiring_soon' => 0,
            'renewed' => 0,
            'errors' => [],
        ];
        
        $subscriptions = Subscription::active()->get();
        
        foreach ($subscriptions as $subscription) {
            try {
                $results['checked']++;
                
                if ($subscription->hasExpired()) {
                    $subscription->update(['status' => 'expired']);
                    $results['expired']++;
                } elseif ($subscription->daysUntilExpiration() <= 7) {
                    $results['expiring_soon']++;
                }
                
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Check subscription status for a specific store.
     */
    public function checkStoreSubscription(Store $store): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return [
                'status' => 'no_subscription',
                'message' => 'Store has no active subscription',
                'requires_action' => true,
            ];
        }
        
        $daysUntilExpiration = $subscription->daysUntilExpiration();
        
        if ($subscription->hasExpired()) {
            $subscription->update(['status' => 'expired']);
            
            return [
                'status' => 'expired',
                'message' => 'Subscription has expired',
                'expired_at' => $subscription->ends_at,
                'requires_action' => true,
            ];
        }
        
        if ($daysUntilExpiration <= 7) {
            return [
                'status' => 'expiring_soon',
                'message' => "Subscription expires in {$daysUntilExpiration} days",
                'expires_at' => $subscription->ends_at,
                'days_remaining' => $daysUntilExpiration,
                'requires_action' => true,
            ];
        }
        
        if ($subscription->onTrial()) {
            $trialDaysRemaining = now()->diffInDays($subscription->trial_ends_at, false);
            
            return [
                'status' => 'on_trial',
                'message' => "Trial period active ({$trialDaysRemaining} days remaining)",
                'trial_ends_at' => $subscription->trial_ends_at,
                'trial_days_remaining' => $trialDaysRemaining,
                'requires_action' => $trialDaysRemaining <= 3,
            ];
        }
        
        return [
            'status' => 'active',
            'message' => 'Subscription is active',
            'expires_at' => $subscription->ends_at,
            'days_remaining' => $daysUntilExpiration,
            'requires_action' => false,
        ];
    }
    
    /**
     * Get stores with subscriptions requiring attention.
     */
    public function getStoresRequiringAttention(): Collection
    {
        return Store::whereHas('subscriptions', function ($query) {
            $query->where('status', 'active')
                  ->where(function ($q) {
                      $q->where('ends_at', '<=', now()->addDays(7))
                        ->orWhere('trial_ends_at', '<=', now()->addDays(3));
                  });
        })->with(['activeSubscription.plan'])->get();
    }
    
    /**
     * Check if store can access a specific feature.
     */
    public function canAccessFeature(Store $store, string $feature): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return [
                'can_access' => false,
                'reason' => 'no_subscription',
                'message' => 'No active subscription found',
            ];
        }
        
        if ($subscription->hasExpired()) {
            return [
                'can_access' => false,
                'reason' => 'subscription_expired',
                'message' => 'Subscription has expired',
            ];
        }
        
        if (!$subscription->plan->hasFeature($feature)) {
            $requiredPlan = $subscription->plan->getRequiredPlanFor($feature);
            
            return [
                'can_access' => false,
                'reason' => 'feature_not_available',
                'message' => "Feature requires {$requiredPlan} plan or higher",
                'required_plan' => $requiredPlan,
            ];
        }
        
        return [
            'can_access' => true,
            'reason' => 'feature_available',
            'message' => 'Feature is available',
        ];
    }
    
    /**
     * Check if store has exceeded usage limits.
     */
    public function checkUsageLimits(Store $store): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return ['status' => 'no_subscription'];
        }
        
        $plan = $subscription->plan;
        $limits = $plan->limits ?? [];
        $violations = [];
        $warnings = [];
        
        foreach ($limits as $feature => $limit) {
            $currentUsage = $store->getCurrentUsage($feature);
            $usagePercentage = $limit > 0 ? ($currentUsage / $limit) * 100 : 0;
            
            if ($currentUsage >= $limit) {
                $violations[] = [
                    'feature' => $feature,
                    'current_usage' => $currentUsage,
                    'limit' => $limit,
                    'percentage' => $usagePercentage,
                ];
            } elseif ($usagePercentage >= 80) {
                $warnings[] = [
                    'feature' => $feature,
                    'current_usage' => $currentUsage,
                    'limit' => $limit,
                    'percentage' => $usagePercentage,
                ];
            }
        }
        
        // Check transaction quota separately
        $transactionStatus = $this->checkTransactionQuota($store);
        
        return [
            'status' => empty($violations) ? 'within_limits' : 'exceeded_limits',
            'violations' => $violations,
            'warnings' => $warnings,
            'transaction_quota' => $transactionStatus,
        ];
    }
    
    /**
     * Check transaction quota status.
     */
    public function checkTransactionQuota(Store $store): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return ['status' => 'no_subscription'];
        }
        
        $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
        
        if (!$usage || !$usage->annual_quota) {
            return [
                'status' => 'unlimited',
                'message' => 'No transaction quota limit',
            ];
        }
        
        $usagePercentage = $usage->getUsagePercentage();
        
        if ($usage->hasExceededQuota()) {
            return [
                'status' => 'exceeded',
                'message' => 'Annual transaction quota exceeded',
                'current_usage' => $usage->current_usage,
                'annual_quota' => $usage->annual_quota,
                'percentage' => $usagePercentage,
                'soft_cap_triggered' => $usage->soft_cap_triggered,
            ];
        }
        
        if ($usagePercentage >= 80) {
            return [
                'status' => 'warning',
                'message' => 'Approaching transaction quota limit',
                'current_usage' => $usage->current_usage,
                'annual_quota' => $usage->annual_quota,
                'percentage' => $usagePercentage,
                'soft_cap_triggered' => $usage->soft_cap_triggered,
            ];
        }
        
        return [
            'status' => 'within_limit',
            'message' => 'Transaction usage within limits',
            'current_usage' => $usage->current_usage,
            'annual_quota' => $usage->annual_quota,
            'percentage' => $usagePercentage,
        ];
    }
    
    /**
     * Get subscription health summary for a store.
     */
    public function getSubscriptionHealth(Store $store): array
    {
        $subscriptionStatus = $this->checkStoreSubscription($store);
        $usageLimits = $this->checkUsageLimits($store);
        $featureAccess = $this->getFeatureAccessSummary($store);
        
        return [
            'subscription' => $subscriptionStatus,
            'usage_limits' => $usageLimits,
            'feature_access' => $featureAccess,
            'overall_health' => $this->calculateOverallHealth($subscriptionStatus, $usageLimits),
        ];
    }
    
    /**
     * Get feature access summary for a store.
     */
    private function getFeatureAccessSummary(Store $store): array
    {
        $features = [
            'inventory_tracking',
            'cogs_calculation',
            'multi_outlet',
            'advanced_reports',
            'monthly_email_reports',
        ];
        
        $access = [];
        
        foreach ($features as $feature) {
            $access[$feature] = $this->canAccessFeature($store, $feature);
        }
        
        return $access;
    }
    
    /**
     * Calculate overall health score.
     */
    private function calculateOverallHealth(array $subscriptionStatus, array $usageLimits): string
    {
        if ($subscriptionStatus['status'] === 'expired' || $subscriptionStatus['status'] === 'no_subscription') {
            return 'critical';
        }
        
        if ($usageLimits['status'] === 'exceeded_limits') {
            return 'warning';
        }
        
        if ($subscriptionStatus['requires_action'] || !empty($usageLimits['warnings'])) {
            return 'attention_needed';
        }
        
        return 'healthy';
    }
}