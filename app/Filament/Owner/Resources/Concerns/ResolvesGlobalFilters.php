<?php

namespace App\Filament\Owner\Resources\Concerns;

use App\Models\User;
use App\Services\GlobalFilterService;

trait ResolvesGlobalFilters
{
    protected static function globalFilter(): GlobalFilterService
    {
        return app(GlobalFilterService::class);
    }

    protected static function currentTenantId(): ?string
    {
        return static::globalFilter()->getCurrentTenantId();
    }

    /**
     * @return array<int, string>
     */
    protected static function currentStoreIds(): array
    {
        return static::globalFilter()->getStoreIdsForCurrentTenant();
    }

    protected static function defaultStoreId(): ?string
    {
        $filter = static::globalFilter();

        return $filter->getCurrentStoreId()
            ?? ($filter->getStoreIdsForCurrentTenant()[0] ?? null);
    }

    /**
     * @return array<int, string>
     */
    protected static function userOptionsForCurrentContext(): array
    {
        $tenantId = static::currentTenantId();

        if (! $tenantId) {
            return [];
        }

        $storeIds = static::currentStoreIds();

        $query = User::query()
            ->whereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));

        if (! empty($storeIds)) {
            $query->where(function ($userQuery) use ($storeIds) {
                $userQuery->whereHas('storeAssignments', fn ($assignment) => $assignment->whereIn('store_id', $storeIds));
            });
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}

