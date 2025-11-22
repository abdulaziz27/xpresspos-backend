<?php

namespace App\Traits;

use App\Models\Subscription;
use App\Models\Plan;

trait HasSubscriptionFeatures
{
    /**
     * Get user's active subscription through tenant (via store).
     * 
     * Model Bisnis: Subscription per Tenant (bukan per Store)
     */
    public function activeSubscription()
    {
        // Get subscription through user's store -> tenant
        if (!$this->store_id) {
            return Subscription::query()->whereRaw('1 = 0'); // Return empty query
        }
        
        $store = $this->store;
        if (!$store || !$store->tenant_id) {
            return Subscription::query()->whereRaw('1 = 0'); // Return empty query
        }
        
        return Subscription::query()
            ->where('tenant_id', $store->tenant_id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest();
    }

    /**
     * Get user's subscription plan
     */
    public function getSubscriptionPlan(): ?Plan
    {
        $subscription = $this->activeSubscription()->first();
        
        if (!$subscription) {
            return $this->getFreePlan();
        }
        
        return $subscription->plan;
    }

    /**
     * Get free/trial plan as default
     */
    protected function getFreePlan(): Plan
    {
        return new Plan([
            'name' => 'Free',
            'slug' => 'free',
            'features' => [],
            'limits' => [
                'stores' => 1,
                'products' => 50,
                'staff' => 2,
                'orders_per_month' => 100,
            ]
        ]);
    }

    /**
     * Check if user has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        $plan = $this->getSubscriptionPlan();
        
        if (!$plan) {
            return false;
        }
        
        $features = is_array($plan->features) ? $plan->features : json_decode($plan->features, true);
        
        return in_array($feature, $features ?? []);
    }

    /**
     * Get limit value for a specific resource
     */
    public function getLimit(string $key): int
    {
        $plan = $this->getSubscriptionPlan();
        
        if (!$plan) {
            return 0;
        }
        
        $limits = is_array($plan->limits) ? $plan->limits : json_decode($plan->limits, true);
        
        return $limits[$key] ?? 0;
    }

    /**
     * Check if user is within limit for a resource
     */
    public function isWithinLimit(string $key, int $current): bool
    {
        $limit = $this->getLimit($key);
        
        // 0 or negative means unlimited
        if ($limit <= 0) {
            return true;
        }
        
        return $current < $limit;
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * Check if user is on free plan
     */
    public function isFreePlan(): bool
    {
        return !$this->hasActiveSubscription();
    }

    /**
     * Get subscription tier name
     */
    public function getSubscriptionTier(): string
    {
        $plan = $this->getSubscriptionPlan();
        return $plan ? $plan->name : 'Free';
    }

    /**
     * Check if user can create more of a resource
     */
    public function canCreate(string $resource): bool
    {
        $limitKey = $resource;
        $currentCount = $this->getCurrentCount($resource);
        
        return $this->isWithinLimit($limitKey, $currentCount);
    }

    /**
     * Get current count of a resource
     */
    public function getCurrentCount(string $resource): int
    {
        // Ensure store relationship is loaded
        if (!$this->relationLoaded('store') && $this->store_id) {
            $this->load('store');
        }
        
        return match($resource) {
            'stores' => $this->storeAssignments()->count(),
            'products' => $this->store ? $this->store->products()->count() : 0,
            'staff' => $this->store ? $this->store->userAssignments()->count() : 0,
            default => 0,
        };
    }

    /**
     * Get usage percentage for a resource
     */
    public function getUsagePercentage(string $resource): int
    {
        $limit = $this->getLimit($resource);
        
        if ($limit <= 0) {
            return 0; // Unlimited
        }
        
        $current = $this->getCurrentCount($resource);
        
        return min(100, (int) (($current / $limit) * 100));
    }
}
