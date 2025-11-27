<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class SupplierPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory.view') && 
               $user->currentTenant()?->id === $supplier->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory.view');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory.view') && 
               $user->currentTenant()?->id === $supplier->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory.view') && 
               $user->currentTenant()?->id === $supplier->tenant_id;
    }
}

