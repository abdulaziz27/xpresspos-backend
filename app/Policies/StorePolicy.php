<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class StorePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        // All users can view stores they have access to
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Store $store): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        // User can view store if they have access to it
        return $user->stores()->where('stores.id', $store->id)->exists() ||
               $user->currentTenant()?->id === $store->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        // Only owners can create stores
        return $user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Store $store): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        // Only owners can update stores
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) {
            return $user->currentTenant()?->id === $store->tenant_id;
        }
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Store $store): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        // Only owners can delete stores, and only if store has no critical data
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) {
            return $user->currentTenant()?->id === $store->tenant_id;
        }
        return false;
    }
}

