<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Models\Store;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;

trait ChecksPlanLimits
{
    /**
     * Check if tenant has a specific feature enabled.
     * 
     * @param Tenant|Store|null $entity Tenant, Store, or null (will resolve from current user)
     * @param string $featureCode Feature code (e.g., 'ALLOW_INVENTORY', 'ALLOW_TABLE_MANAGEMENT')
     * @return bool
     */
    protected function hasFeature($entity, string $featureCode): bool
    {
        $planLimitService = app(PlanLimitService::class);
        
        if (!$entity) {
            $entity = $this->resolveEntityFromRequest();
        }
        
        if (!$entity) {
            return false;
        }
        
        return $planLimitService->hasFeature($entity, $featureCode);
    }

    /**
     * Check if tenant can perform an action (with limit check).
     * 
     * @param Tenant|Store|null $entity Tenant, Store, or null
     * @param string $action Action code (e.g., 'create_product', 'use_inventory')
     * @param int $currentCount Current count of the resource
     * @return array{allowed: bool, reason: string|null, message: string|null, limit: int|null}
     */
    protected function canPerformAction($entity, string $action, int $currentCount = 0): array
    {
        $planLimitService = app(PlanLimitService::class);
        
        if (!$entity) {
            $entity = $this->resolveEntityFromRequest();
        }
        
        if (!$entity) {
            return [
                'allowed' => false,
                'reason' => 'no_entity',
                'message' => 'Cannot resolve tenant/store from request',
                'limit' => null,
            ];
        }
        
        return $planLimitService->canPerformAction($entity, $action, $currentCount);
    }

    /**
     * Return JSON error response for feature not available.
     */
    protected function featureNotAvailableResponse(string $featureName, string $recommendedPlan = 'Pro'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FEATURE_NOT_AVAILABLE',
                'message' => "Feature '{$featureName}' is not available in your current plan.",
                'upgrade_required' => true,
                'recommended_plan' => $recommendedPlan,
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ], 403);
    }

    /**
     * Return JSON error response for limit exceeded.
     */
    protected function limitExceededResponse(string $resourceName, int $current, int $limit, string $recommendedPlan = 'Pro'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'LIMIT_EXCEEDED',
                'message' => "You have reached your {$resourceName} limit ({$current} / {$limit}).",
                'upgrade_required' => true,
                'recommended_plan' => $recommendedPlan,
                'current_count' => $current,
                'limit' => $limit,
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1'
            ]
        ], 403);
    }

    /**
     * Resolve entity (Tenant or Store) from current request/user.
     */
    protected function resolveEntityFromRequest()
    {
        $user = auth()->user() ?? request()->user();
        
        if (!$user) {
            return null;
        }

        // Try to get store first (more specific)
        $store = $user->store();
        if ($store) {
            return $store;
        }

        // Fallback to tenant
        $tenant = $user->currentTenant();
        if ($tenant) {
            return $tenant;
        }

        return null;
    }
}

