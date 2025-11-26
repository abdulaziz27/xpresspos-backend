<?php

namespace App\Services;

use App\Mail\PlanUsageWarning;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlanUsageNotificationService
{
    protected PlanLimitService $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    /**
     * Check usage for all tenants and send notifications if needed.
     */
    public function checkAndNotifyAllTenants(): void
    {
        $tenants = Tenant::with('plan')->whereNotNull('plan_id')->get();

        foreach ($tenants as $tenant) {
            try {
                $this->checkAndNotifyTenant($tenant);
            } catch (\Exception $e) {
                Log::error('Failed to check usage for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check usage for a specific tenant and send notifications if needed.
     */
    public function checkAndNotifyTenant(Tenant $tenant): void
    {
        if (!$tenant->plan) {
            return;
        }

        // Check each feature type
        $this->checkFeatureUsage($tenant, 'products');
        $this->checkFeatureUsage($tenant, 'staff');
        $this->checkFeatureUsage($tenant, 'stores');
        $this->checkFeatureUsage($tenant, 'transactions');
    }

    /**
     * Check usage for a specific feature type.
     */
    protected function checkFeatureUsage(Tenant $tenant, string $featureType): void
    {
        $featureCodeMap = [
            'products' => 'MAX_PRODUCTS',
            'staff' => 'MAX_STAFF',
            'stores' => 'MAX_STORES',
            'transactions' => 'MAX_TRANSACTIONS_PER_MONTH',
        ];

        $featureCode = $featureCodeMap[$featureType] ?? null;
        if (!$featureCode) {
            return;
        }

        // Get current usage
        $currentUsage = $this->getCurrentUsage($tenant, $featureType);
        
        // Get limit
        $limit = $this->planLimitService->limit($tenant, $featureCode);
        
        // Skip if unlimited
        if ($limit === -1 || $limit === null) {
            return;
        }

        // Calculate percentage
        $usagePercentage = ($currentUsage / $limit) * 100;

        // Check thresholds
        $this->checkThreshold($tenant, $featureType, $currentUsage, $limit, $usagePercentage, 80);
        $this->checkThreshold($tenant, $featureType, $currentUsage, $limit, $usagePercentage, 100);
    }

    /**
     * Check if usage has reached a threshold and send notification if needed.
     */
    protected function checkThreshold(
        Tenant $tenant,
        string $featureType,
        int $currentUsage,
        int $limit,
        float $usagePercentage,
        int $threshold
    ): void {
        // Check if usage has reached or exceeded threshold
        if ($usagePercentage < $threshold) {
            return;
        }

        // Check if notification was already sent for this threshold
        $notificationKey = "usage_warning_{$featureType}_{$threshold}";
        $lastNotification = cache()->get("tenant_{$tenant->id}_{$notificationKey}");

        // If notification was sent in the last 24 hours, skip
        if ($lastNotification && $lastNotification > now()->subDay()) {
            return;
        }

        // Send notification
        $this->sendNotification($tenant, $featureType, $currentUsage, $limit, $usagePercentage, (string) $threshold);

        // Mark notification as sent
        cache()->put("tenant_{$tenant->id}_{$notificationKey}", now(), now()->addDay());
    }

    /**
     * Get current usage for a feature type.
     */
    protected function getCurrentUsage(Tenant $tenant, string $featureType): int
    {
        return match ($featureType) {
            'products' => Product::where('tenant_id', $tenant->id)->count(),
            'staff' => $this->getStaffCount($tenant),
            'stores' => Store::where('tenant_id', $tenant->id)->count(),
            'transactions' => $this->getTransactionCount($tenant),
            default => 0,
        };
    }

    /**
     * Get staff count for tenant.
     */
    protected function getStaffCount(Tenant $tenant): int
    {
        $storeIds = Store::where('tenant_id', $tenant->id)->pluck('id');
        
        return User::whereHas('storeAssignments', function ($q) use ($storeIds) {
            $q->whereIn('store_id', $storeIds);
        })
        ->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'admin_sistem');
        })
        ->distinct()
        ->count();
    }

    /**
     * Get transaction count for current month.
     */
    protected function getTransactionCount(Tenant $tenant): int
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        return Order::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->count();
    }

    /**
     * Send usage warning notification to tenant owners.
     */
    protected function sendNotification(
        Tenant $tenant,
        string $featureType,
        int $currentUsage,
        int $limit,
        float $usagePercentage,
        string $threshold
    ): void {
        // Get tenant owners
        $owners = User::whereHas('tenants', function ($q) use ($tenant) {
            $q->where('tenants.id', $tenant->id);
        })
        ->whereHas('roles', function ($q) {
            $q->where('name', 'owner');
        })
        ->get();

        if ($owners->isEmpty()) {
            Log::warning('No owners found for tenant, cannot send usage warning', [
                'tenant_id' => $tenant->id,
                'feature_type' => $featureType,
            ]);
            return;
        }

        // Send email to each owner
        foreach ($owners as $owner) {
            try {
                Mail::to($owner->email)->send(
                    new PlanUsageWarning(
                        tenant: $tenant,
                        featureType: $featureType,
                        currentUsage: $currentUsage,
                        limit: $limit,
                        usagePercentage: $usagePercentage,
                        threshold: $threshold
                    )
                );

                Log::info('Usage warning email sent', [
                    'tenant_id' => $tenant->id,
                    'owner_email' => $owner->email,
                    'feature_type' => $featureType,
                    'usage_percentage' => $usagePercentage,
                    'threshold' => $threshold,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send usage warning email', [
                    'tenant_id' => $tenant->id,
                    'owner_email' => $owner->email,
                    'feature_type' => $featureType,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

