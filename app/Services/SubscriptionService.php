<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionService
{
    /**
     * Create a new subscription for a store.
     */
    public function createSubscription(Store $store, Plan $plan, array $options = []): Subscription
    {
        $billingCycle = $options['billing_cycle'] ?? 'monthly';
        $startsAt = $options['starts_at'] ?? now();
        $trialDays = $options['trial_days'] ?? 0;
        
        // Calculate end date based on billing cycle
        $endsAt = $billingCycle === 'annual' 
            ? Carbon::parse($startsAt)->addYear()
            : Carbon::parse($startsAt)->addMonth();
        
        // Calculate trial end date
        $trialEndsAt = $trialDays > 0 
            ? Carbon::parse($startsAt)->addDays($trialDays)
            : null;
        
        // Determine amount based on billing cycle
        $amount = $billingCycle === 'annual' 
            ? $plan->annual_price ?? $plan->price * 12
            : $plan->price;
        
        // Cancel existing active subscription
        $this->cancelActiveSubscription($store);
        
        // Create new subscription
        $subscription = $store->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'amount' => $amount,
            'metadata' => $options['metadata'] ?? [],
        ]);
        
        // Initialize usage tracking
        $this->initializeUsageTracking($subscription);
        
        return $subscription;
    }
    
    /**
     * Upgrade a subscription to a new plan.
     */
    public function upgradeSubscription(Subscription $subscription, Plan $newPlan): Subscription
    {
        // Calculate prorated amount and new end date
        $remainingDays = now()->diffInDays($subscription->ends_at);
        $proratedAmount = $this->calculateProratedAmount($subscription, $newPlan, $remainingDays);
        
        // Update subscription
        $subscription->update([
            'plan_id' => $newPlan->id,
            'amount' => $proratedAmount,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'upgraded_from' => $subscription->plan_id,
                'upgraded_at' => now(),
            ]),
        ]);
        
        // Update usage limits
        $this->updateUsageLimits($subscription);
        
        return $subscription->fresh();
    }
    
    /**
     * Downgrade a subscription to a new plan.
     */
    public function downgradeSubscription(Subscription $subscription, Plan $newPlan): Subscription
    {
        // Check if current usage exceeds new plan limits
        $this->validateDowngradeConstraints($subscription, $newPlan);
        
        // Schedule downgrade for next billing cycle
        $subscription->update([
            'metadata' => array_merge($subscription->metadata ?? [], [
                'scheduled_downgrade' => [
                    'plan_id' => $newPlan->id,
                    'effective_date' => $subscription->ends_at,
                    'scheduled_at' => now(),
                ],
            ]),
        ]);
        
        return $subscription->fresh();
    }
    
    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): Subscription
    {
        if ($immediately) {
            $subscription->update([
                'status' => 'cancelled',
                'ends_at' => now(),
            ]);
        } else {
            // Cancel at end of billing period
            $subscription->update([
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'cancelled_at' => now(),
                    'cancellation_effective_date' => $subscription->ends_at,
                ]),
            ]);
        }
        
        return $subscription->fresh();
    }
    
    /**
     * Renew a subscription.
     */
    public function renewSubscription(Subscription $subscription): Subscription
    {
        $newEndsAt = $subscription->billing_cycle === 'annual'
            ? $subscription->ends_at->addYear()
            : $subscription->ends_at->addMonth();
        
        $subscription->update([
            'ends_at' => $newEndsAt,
            'status' => 'active',
        ]);
        
        // Reset annual usage counters if it's a new subscription year
        if ($subscription->billing_cycle === 'annual') {
            $this->resetAnnualUsage($subscription);
        }
        
        return $subscription->fresh();
    }
    
    /**
     * Check subscription status and update if needed.
     */
    public function checkSubscriptionStatus(Subscription $subscription): Subscription
    {
        // Check if subscription has expired
        if ($subscription->hasExpired() && $subscription->status === 'active') {
            $subscription->update(['status' => 'expired']);
        }
        
        // Process scheduled downgrades
        if ($this->hasScheduledDowngrade($subscription) && $subscription->ends_at->isPast()) {
            $this->processScheduledDowngrade($subscription);
        }
        
        return $subscription->fresh();
    }
    
    /**
     * Get subscription usage summary.
     */
    public function getUsageSummary(Subscription $subscription): array
    {
        $usage = $subscription->usage()->get();
        $plan = $subscription->plan;
        
        $summary = [];
        
        foreach ($usage as $usageRecord) {
            $limit = $plan->getLimit($usageRecord->feature_type);
            
            $summary[$usageRecord->feature_type] = [
                'current_usage' => $usageRecord->current_usage,
                'limit' => $limit,
                'annual_quota' => $usageRecord->annual_quota,
                'usage_percentage' => $usageRecord->getUsagePercentage(),
                'soft_cap_triggered' => $usageRecord->soft_cap_triggered,
                'has_exceeded_quota' => $usageRecord->hasExceededQuota(),
            ];
        }
        
        return $summary;
    }
    
    /**
     * Get subscriptions expiring soon.
     */
    public function getExpiringSoon(int $days = 7): Collection
    {
        return Subscription::active()
            ->expiringSoon($days)
            ->with(['store', 'plan'])
            ->get();
    }
    
    /**
     * Initialize usage tracking for a new subscription.
     */
    private function initializeUsageTracking(Subscription $subscription): void
    {
        $plan = $subscription->plan;
        $limits = $plan->limits ?? [];
        
        // Define trackable features
        $trackableFeatures = [
            'products' => $limits['products'] ?? null,
            'transactions' => $limits['transactions'] ?? null,
            'users' => $limits['users'] ?? null,
            'outlets' => $limits['outlets'] ?? null,
        ];
        
        foreach ($trackableFeatures as $feature => $limit) {
            $subscription->usage()->create([
                'feature_type' => $feature,
                'current_usage' => 0,
                'annual_quota' => $feature === 'transactions' ? $limit : null,
                'subscription_year_start' => $subscription->starts_at,
                'subscription_year_end' => $subscription->billing_cycle === 'annual' 
                    ? $subscription->ends_at 
                    : $subscription->starts_at->copy()->addYear(),
            ]);
        }
    }
    
    /**
     * Update usage limits after plan change.
     */
    private function updateUsageLimits(Subscription $subscription): void
    {
        $plan = $subscription->plan;
        $limits = $plan->limits ?? [];
        
        foreach ($subscription->usage as $usage) {
            $newLimit = $limits[$usage->feature_type] ?? null;
            
            if ($usage->feature_type === 'transactions') {
                $usage->update(['annual_quota' => $newLimit]);
            }
        }
    }
    
    /**
     * Cancel active subscription for a store.
     */
    private function cancelActiveSubscription(Store $store): void
    {
        $activeSubscription = $store->subscriptions()->where('status', 'active')->first();
        
        if ($activeSubscription) {
            $activeSubscription->update(['status' => 'cancelled']);
        }
    }
    
    /**
     * Calculate prorated amount for plan change.
     */
    private function calculateProratedAmount(Subscription $subscription, Plan $newPlan, int $remainingDays): float
    {
        $currentPlan = $subscription->plan;
        $totalDays = $subscription->billing_cycle === 'annual' ? 365 : 30;
        
        // Calculate daily rates
        $currentDailyRate = $subscription->amount / $totalDays;
        $newDailyRate = ($subscription->billing_cycle === 'annual' 
            ? ($newPlan->annual_price ?? $newPlan->price * 12)
            : $newPlan->price) / $totalDays;
        
        // Calculate prorated difference
        $proratedDifference = ($newDailyRate - $currentDailyRate) * $remainingDays;
        
        return $subscription->amount + $proratedDifference;
    }
    
    /**
     * Validate if downgrade is possible based on current usage.
     */
    private function validateDowngradeConstraints(Subscription $subscription, Plan $newPlan): void
    {
        $store = $subscription->store;
        $newLimits = $newPlan->limits ?? [];
        
        foreach ($newLimits as $feature => $limit) {
            $currentUsage = $store->getCurrentUsage($feature);
            
            if ($currentUsage > $limit) {
                throw new \InvalidArgumentException(
                    "Cannot downgrade: Current {$feature} usage ({$currentUsage}) exceeds new plan limit ({$limit})"
                );
            }
        }
        
        // Check if new plan supports current features
        $currentFeatures = $subscription->plan->features ?? [];
        $newFeatures = $newPlan->features ?? [];
        
        $missingFeatures = array_diff($currentFeatures, $newFeatures);
        if (!empty($missingFeatures)) {
            throw new \InvalidArgumentException(
                "Cannot downgrade: New plan doesn't support features: " . implode(', ', $missingFeatures)
            );
        }
    }
    
    /**
     * Reset annual usage counters.
     */
    private function resetAnnualUsage(Subscription $subscription): void
    {
        $subscription->usage()->each(function ($usage) {
            $usage->resetForNewYear();
        });
    }
    
    /**
     * Check if subscription has scheduled downgrade.
     */
    private function hasScheduledDowngrade(Subscription $subscription): bool
    {
        return isset($subscription->metadata['scheduled_downgrade']);
    }
    
    /**
     * Process scheduled downgrade.
     */
    private function processScheduledDowngrade(Subscription $subscription): void
    {
        $downgradeInfo = $subscription->metadata['scheduled_downgrade'];
        $newPlan = Plan::find($downgradeInfo['plan_id']);
        
        if ($newPlan) {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'amount' => $subscription->billing_cycle === 'annual' 
                    ? ($newPlan->annual_price ?? $newPlan->price * 12)
                    : $newPlan->price,
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'downgraded_from' => $subscription->plan_id,
                    'downgraded_at' => now(),
                ]),
            ]);
            
            // Remove scheduled downgrade
            $metadata = $subscription->metadata;
            unset($metadata['scheduled_downgrade']);
            $subscription->update(['metadata' => $metadata]);
            
            // Update usage limits
            $this->updateUsageLimits($subscription);
        }
    }
}