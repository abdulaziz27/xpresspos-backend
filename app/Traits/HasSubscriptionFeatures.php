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
        $store = $this->store();
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
        // REFACTOR: Get plan from tenant directly
        // This ensures we rely on the database truth, not on-the-fly objects
        $tenant = $this->currentTenant();
        
        if (!$tenant) {
             return null;
        }

        if (!$tenant->plan) {
             // Fallback to finding Free plan in DB if tenant has no plan set
             // (Should not happen after migration)
             return Plan::where('slug', 'free')->first();
        }
        
        return $tenant->plan;
    }

    /**
     * Check if user has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        $tenant = $this->currentTenant();

        if (!$tenant) {
            return false;
        }

        return app(\App\Services\PlanLimitService::class)->hasFeature($tenant, $feature);
    }

    /**
     * Get limit value for a specific resource
     */
    public function getLimit(string $key): int
    {
        $tenant = $this->currentTenant();

        if (!$tenant) {
            return 0;
        }

        $limit = app(\App\Services\PlanLimitService::class)->limit($tenant, $key);

        if ($limit === null) {
            return 0;
        }

        return $limit === -1 ? 0 : $limit;
    }

    /**
     * Check if user is within limit for a resource
     */
    public function isWithinLimit(string $key, int $current): bool
    {
        $limit = $this->getLimit($key);
        
        // 0 berarti unlimited (representasi fallback di atas).
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
        $plan = $this->getSubscriptionPlan();
        return $plan && $plan->slug === 'free';
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
        if ($resource === 'stores') {
            return $this->storeAssignments()->count();
        }
        
        if ($resource === 'products') {
            $store = $this->store();
            if (!$store || !$store->tenant_id) {
                return 0;
            }
            return \App\Models\Product::where('tenant_id', $store->tenant_id)->count();
        }
        
        if ($resource === 'staff') {
            $store = $this->store();
            return $store ? $store->userAssignments()->count() : 0;
        }
        
        return 0;
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
