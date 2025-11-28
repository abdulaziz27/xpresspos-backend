<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SubscriptionUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\PlanFeatureResolver;

/**
 * Service untuk handle plan limits dan feature gating.
 * 
 * Model Bisnis:
 * - Subscription per Tenant (bukan per Store)
 * - Satu tenant bisa punya banyak store, semua dilindungi oleh satu subscription yang sama
 */
class PlanLimitService
{
    /**
     * Check if tenant has a specific feature enabled.
     * 
     * @param Tenant|Store $entity Tenant atau Store (akan di-resolve ke tenant)
     * @param string $featureCode Feature code (ALLOW_LOYALTY, ALLOW_MULTI_STORE, dll)
     * @return bool
     */
    public function hasFeature(Tenant|Store $entity, string $featureCode): bool
    {
        $featureCode = PlanFeatureResolver::normalizeFeatureCode($featureCode) ?? $featureCode;

        $tenant = $this->resolveTenant($entity);
        $plan = $this->getActivePlan($tenant);

        if (!$plan) {
            return false;
        }

        // Check dari plan_features (normalized)
        $feature = PlanFeature::where('plan_id', $plan->id)
            ->where('feature_code', $featureCode)
            ->first();

        if (!$feature) {
            return false;
        }

        // Untuk feature flags, gunakan is_enabled
        return $feature->is_enabled;
    }

    /**
     * Get limit value for a specific feature.
     * 
     * @param Tenant|Store $entity Tenant atau Store
     * @param string $featureCode Feature code (MAX_STORES, MAX_PRODUCTS, dll)
     * @return int|null Returns limit value (integer), -1 for unlimited, null if not found
     */
    public function limit(Tenant|Store $entity, string $featureCode): ?int
    {
        $featureCode = PlanFeatureResolver::normalizeLimitCode($featureCode) ?? $featureCode;

        $tenant = $this->resolveTenant($entity);
        $plan = $this->getActivePlan($tenant);

        if (!$plan) {
            return null;
        }

        // Get dari plan_features
        $feature = PlanFeature::where('plan_id', $plan->id)
            ->where('feature_code', $featureCode)
            ->first();

        if (!$feature || !$feature->is_enabled) {
            return null;
        }

        // Cast limit_value ke integer
        if (is_null($feature->limit_value)) {
            return -1; // unlimited
        }

        $baseLimit = (int) $feature->limit_value;

        // -1 atau 0 = unlimited
        if ($baseLimit <= 0) {
            return -1;
        }

        // Add add-ons to the base limit
        $addOnBonus = $this->getAddOnBonus($tenant, $featureCode);
        
        return $baseLimit + $addOnBonus;
    }

    /**
     * Get total additional limit from active add-ons for a feature.
     */
    protected function getAddOnBonus(Tenant $tenant, string $featureCode): int
    {
        $activeAddOns = $tenant->activeAddOns()
            ->whereHas('addOn', function ($q) use ($featureCode) {
                $q->where('feature_code', $featureCode)
                  ->where('is_active', true);
            })
            ->with('addOn')
            ->get();

        $totalBonus = 0;
        foreach ($activeAddOns as $tenantAddOn) {
            // quantity = jumlah unit add-on yang dibeli
            // addOn->quantity = jumlah limit per unit
            $totalBonus += $tenantAddOn->quantity * $tenantAddOn->addOn->quantity;
        }

        return $totalBonus;
    }

    /**
     * Check if tenant is within limit for a resource.
     * 
     * @param Tenant|Store $entity Tenant atau Store
     * @param string $featureCode Feature code (MAX_STORES, MAX_PRODUCTS, dll)
     * @param int $currentCount Current count of the resource
     * @return bool
     */
    public function isWithinLimit(Tenant|Store $entity, string $featureCode, int $currentCount): bool
    {
        $limit = $this->limit($entity, $featureCode);

        // null = feature not found
        if ($limit === null) {
            return false;
        }

        // -1 = unlimited
        if ($limit === -1) {
            return true;
        }

        return $currentCount < $limit;
    }

    /**
     * Track usage for a feature (soft cap / quota tracking).
     * 
     * @param Tenant|Store $entity Tenant atau Store
     * @param string $featureType Feature type untuk tracking (transactions, orders, dll)
     * @param int $amount Amount to increment (default: 1)
     * @return SubscriptionUsage|null
     */
    public function trackUsage(Tenant|Store $entity, string $featureType, int $amount = 1): ?SubscriptionUsage
    {
        $tenant = $this->resolveTenant($entity);
        $subscription = $this->getActiveSubscription($tenant);

        if (!$subscription) {
            Log::warning('Cannot track usage: no active subscription', [
                'tenant_id' => $tenant->id,
                'feature_type' => $featureType,
            ]);
            return null;
        }

        // Get or create usage record
        $usage = SubscriptionUsage::firstOrCreate(
            [
                'subscription_id' => $subscription->id,
                'feature_type' => $featureType,
            ],
            [
                'current_usage' => 0,
                'annual_quota' => $this->getQuotaForFeatureType($subscription, $featureType),
                'subscription_year_start' => $subscription->starts_at->startOfMonth(), // Reset bulanan
                'subscription_year_end' => $subscription->starts_at->endOfMonth(),
            ]
        );

        // Increment usage
        $usage->increment('current_usage', $amount);

        // Check soft cap thresholds
        $this->checkSoftCap($usage);

        return $usage->fresh();
    }

    /**
     * Get current usage for a feature.
     * 
     * @param Tenant|Store $entity Tenant atau Store
     * @param string $featureType Feature type (transactions, orders, dll)
     * @return array{current: int, quota: int|null, percentage: float|null}
     */
    public function getUsage(Tenant|Store $entity, string $featureType): array
    {
        $tenant = $this->resolveTenant($entity);
        $subscription = $this->getActiveSubscription($tenant);

        if (!$subscription) {
            return [
                'current' => 0,
                'quota' => null,
                'percentage' => null,
            ];
        }

        $usage = SubscriptionUsage::where('subscription_id', $subscription->id)
            ->where('feature_type', $featureType)
            ->first();

        if (!$usage) {
            return [
                'current' => 0,
                'quota' => null,
                'percentage' => null,
            ];
        }

        $quota = $usage->annual_quota;
        $percentage = $quota && $quota > 0 
            ? ($usage->current_usage / $quota) * 100 
            : null;

        return [
            'current' => $usage->current_usage,
            'quota' => $quota,
            'percentage' => $percentage,
        ];
    }

    /**
     * Check if tenant can perform an action (combines feature check + limit check).
     * 
     * @param Tenant|Store $entity Tenant atau Store
     * @param string $action Action code (create_store, create_product, create_staff, dll)
     * @param int $currentCount Current count of the resource
     * @return array{allowed: bool, reason: string|null, message: string|null, limit: int|null}
     */
    public function canPerformAction(Tenant|Store $entity, string $action, int $currentCount = 0): array
    {
        $tenant = $this->resolveTenant($entity);
        $subscription = $this->getActiveSubscription($tenant);

        // Check subscription exists
        if (!$subscription) {
            return [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'No active subscription found',
                'limit' => null,
            ];
        }

        // Check subscription status
        if ($subscription->hasExpired()) {
            return [
                'allowed' => false,
                'reason' => 'subscription_expired',
                'message' => 'Subscription has expired',
                'limit' => null,
            ];
        }

        // Map action to feature code
        $featureCode = $this->mapActionToFeatureCode($action);
        if (!$featureCode) {
            return [
                'allowed' => false,
                'reason' => 'invalid_action',
                'message' => "Unknown action: {$action}",
                'limit' => null,
            ];
        }

        // Check feature access (for ALLOW_* features)
        if (str_starts_with($featureCode, 'ALLOW_')) {
            $hasFeature = $this->hasFeature($tenant, $featureCode);
            return [
                'allowed' => $hasFeature,
                'reason' => $hasFeature ? null : 'feature_not_available',
                'message' => $hasFeature ? null : "Feature not available in current plan",
                'limit' => null,
            ];
        }

        // Check limit (for MAX_* features)
        if (str_starts_with($featureCode, 'MAX_')) {
            $limit = $this->limit($tenant, $featureCode);
            $withinLimit = $this->isWithinLimit($tenant, $featureCode, $currentCount);

            return [
                'allowed' => $withinLimit,
                'reason' => $withinLimit ? null : 'limit_exceeded',
                'message' => $withinLimit ? null : "Limit exceeded: {$currentCount} / {$limit}",
                'limit' => $limit,
            ];
        }

        return [
            'allowed' => false,
            'reason' => 'invalid_feature_code',
            'message' => "Invalid feature code: {$featureCode}",
            'limit' => null,
        ];
    }

    /**
     * Resolve entity to Tenant.
     */
    protected function resolveTenant(Tenant|Store $entity): Tenant
    {
        if ($entity instanceof Tenant) {
            return $entity;
        }

        // If Store, get tenant via tenant_id
        if ($entity->tenant_id) {
            return Tenant::findOrFail($entity->tenant_id);
        }

        throw new \RuntimeException('Store does not have a tenant_id');
    }

    /**
     * Get active subscription for tenant.
     */
    protected function getActiveSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->activeSubscription();
    }

    /**
     * Get active plan for tenant.
     */
    protected function getActivePlan(Tenant $tenant): ?Plan
    {
        // REFACTOR: Use direct plan relationship from tenant
        // This is the single source of truth for "Current Plan"
        if ($tenant->plan) {
            return $tenant->plan;
        }

        // Fallback for legacy data (should be covered by migration)
        $subscription = $this->getActiveSubscription($tenant);
        return $subscription?->plan;
    }

    /**
     * Get quota for feature type from plan.
     */
    protected function getQuotaForFeatureType(Subscription $subscription, string $featureType): ?int
    {
        $tenant = $subscription->tenant;
        
        // Map feature_type to feature_code
        // Catatan: Pastikan feature_code ada di plan_features untuk semua plan
        $featureCodeMap = [
            'transactions' => ['MAX_TRANSACTIONS_PER_MONTH', 'MAX_TRANSACTIONS_PER_YEAR'],
            'stores' => ['MAX_STORES'],
            'products' => ['MAX_PRODUCTS'],
            'staff' => ['MAX_STAFF'],
            // Add more mappings as needed
        ];

        $featureCodes = $featureCodeMap[$featureType] ?? [];

        foreach ($featureCodes as $code) {
            $limit = $this->limit($tenant, $code);

            if ($limit !== null) {
                return $limit;
            }
        }

        return null;
    }

    /**
     * Check soft cap thresholds and trigger notifications if needed.
     * 
     * Note: Soft cap hanya trigger sekali saat pertama kali lewat ambang (default 80%).
     * Untuk multiple thresholds (80%, 90%, 100%), perlu field tambahan di subscription_usage.
     */
    protected function checkSoftCap(SubscriptionUsage $usage): void
    {
        if (!$usage->annual_quota || $usage->annual_quota <= 0) {
            return; // Unlimited
        }

        // Skip jika sudah pernah trigger
        if ($usage->soft_cap_triggered) {
            return;
        }

        $percentage = ($usage->current_usage / $usage->annual_quota) * 100;

        // Soft cap threshold (default: 80%)
        // Bisa di-config via config('subscription.soft_cap_threshold', 80)
        $threshold = config('subscription.soft_cap_threshold', 80);

        if ($percentage >= $threshold) {
            $usage->update([
                'soft_cap_triggered' => true,
                'soft_cap_triggered_at' => now(),
            ]);

            // Dispatch notification job
            // SendQuotaWarningNotification::dispatch($usage->subscription, $usage, $threshold);
            
            Log::info('Soft cap triggered', [
                'subscription_id' => $usage->subscription_id,
                'feature_type' => $usage->feature_type,
                'threshold' => $threshold,
                'usage_percentage' => round($percentage, 2),
            ]);
        }
    }

    /**
     * Map action code to feature code.
     */
    protected function mapActionToFeatureCode(string $action): ?string
    {
        $mapping = [
            'create_store' => 'MAX_STORES',
            'create_product' => 'MAX_PRODUCTS',
            'create_staff' => 'MAX_STAFF',
            'create_transaction' => 'MAX_TRANSACTIONS_PER_MONTH',
            'use_loyalty' => 'ALLOW_LOYALTY',
            'use_multi_store' => 'ALLOW_MULTI_STORE',
            'use_api' => 'ALLOW_API_ACCESS',
            
            // New features per Marketing Spec
            'use_inventory' => 'ALLOW_INVENTORY',
            'use_table_management' => 'ALLOW_TABLE_MANAGEMENT',
            'use_payment_gateway' => 'ALLOW_PAYMENT_GATEWAY',
            'use_auto_backup' => 'ALLOW_AUTO_BACKUP',
            'use_ai_analytics' => 'ALLOW_AI_ANALYTICS',
        ];

        return $mapping[$action] ?? null;
    }
}

