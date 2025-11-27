<?php

namespace App\Policies;

use App\Models\InventoryAdjustment;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class InventoryAdjustmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory_adjustments.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryAdjustment $inventoryAdjustment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory_adjustments.view') && 
               $user->stores()->where('stores.id', $inventoryAdjustment->store_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory_adjustments.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryAdjustment $inventoryAdjustment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        // Only allow update if status is draft
        if ($inventoryAdjustment->status !== InventoryAdjustment::STATUS_DRAFT) {
            return false;
        }
        return $user->hasPermissionTo('inventory_adjustments.update') && 
               $user->stores()->where('stores.id', $inventoryAdjustment->store_id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryAdjustment $inventoryAdjustment): bool
    {
        // Only allow delete if status is draft
        if ($inventoryAdjustment->status !== InventoryAdjustment::STATUS_DRAFT) {
            return false;
        }
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory_adjustments.delete') && 
               $user->stores()->where('stores.id', $inventoryAdjustment->store_id)->exists();
    }
}

