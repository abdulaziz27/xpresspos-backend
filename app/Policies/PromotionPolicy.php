<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class PromotionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('promotions.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Promotion $promotion): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        $hasStoreAccess = $promotion->store_id 
            ? $user->stores()->where('stores.id', $promotion->store_id)->exists()
            : true; // Global promotion
        return $user->hasPermissionTo('promotions.view') && 
               $user->currentTenant()?->id === $promotion->tenant_id &&
               $hasStoreAccess;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('promotions.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Promotion $promotion): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        $hasStoreAccess = $promotion->store_id 
            ? $user->stores()->where('stores.id', $promotion->store_id)->exists()
            : true; // Global promotion
        return $user->hasPermissionTo('promotions.update') && 
               $user->currentTenant()?->id === $promotion->tenant_id &&
               $hasStoreAccess;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Promotion $promotion): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        $hasStoreAccess = $promotion->store_id 
            ? $user->stores()->where('stores.id', $promotion->store_id)->exists()
            : true; // Global promotion
        return $user->hasPermissionTo('promotions.delete') && 
               $user->currentTenant()?->id === $promotion->tenant_id &&
               $hasStoreAccess;
    }
}

