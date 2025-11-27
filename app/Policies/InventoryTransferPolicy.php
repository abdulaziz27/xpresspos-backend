<?php

namespace App\Policies;

use App\Models\InventoryTransfer;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class InventoryTransferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory_transfers.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventoryTransfer $inventoryTransfer): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        // User can view if they have access to either from_store or to_store
        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
        return $user->hasPermissionTo('inventory_transfers.view') && 
               (in_array($inventoryTransfer->from_store_id, $userStoreIds) || 
                in_array($inventoryTransfer->to_store_id, $userStoreIds));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('inventory_transfers.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventoryTransfer $inventoryTransfer): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        // Only allow update if status is draft or approved
        if (!in_array($inventoryTransfer->status, [InventoryTransfer::STATUS_DRAFT, InventoryTransfer::STATUS_APPROVED])) {
            return false;
        }
        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
        return $user->hasPermissionTo('inventory_transfers.update') && 
               in_array($inventoryTransfer->from_store_id, $userStoreIds);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventoryTransfer $inventoryTransfer): bool
    {
        // Only allow delete if status is draft
        if ($inventoryTransfer->status !== InventoryTransfer::STATUS_DRAFT) {
            return false;
        }
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
        return $user->hasPermissionTo('inventory_transfers.delete') && 
               in_array($inventoryTransfer->from_store_id, $userStoreIds);
    }
}

