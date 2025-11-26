<?php

namespace App\Filament\Traits;

use App\Services\PlanLimitService;

trait HasPlanBasedNavigation
{
    /**
     * Check if tenant has a specific feature enabled.
     * Use this in shouldRegisterNavigation() to hide menu items.
     * 
     * @param string $featureCode Feature code (e.g., 'ALLOW_INVENTORY', 'ALLOW_TABLE_MANAGEMENT')
     * @return bool
     */
    protected static function hasPlanFeature(string $featureCode): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $tenant = $user->currentTenant();
        if (!$tenant) {
            return false;
        }

        $planLimitService = app(PlanLimitService::class);
        return $planLimitService->hasFeature($tenant, $featureCode);
    }

    /**
     * Check if tenant can perform an action (with limit check).
     * Use this in canCreate() to prevent creation.
     * 
     * @param string $action Action code (e.g., 'create_product', 'use_inventory')
     * @param int $currentCount Current count of the resource
     * @return array{allowed: bool, reason: string|null, message: string|null, limit: int|null}
     */
    protected static function canPerformPlanAction(string $action, int $currentCount = 0): array
    {
        $user = auth()->user();
        if (!$user) {
            return [
                'allowed' => false,
                'reason' => 'no_user',
                'message' => 'User not authenticated',
                'limit' => null,
            ];
        }

        $tenant = $user->currentTenant();
        if (!$tenant) {
            return [
                'allowed' => false,
                'reason' => 'no_tenant',
                'message' => 'No tenant found',
                'limit' => null,
            ];
        }

        $planLimitService = app(PlanLimitService::class);
        return $planLimitService->canPerformAction($tenant, $action, $currentCount);
    }
}

