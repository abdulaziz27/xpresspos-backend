<?php

namespace App\Services;

use App\Enums\AssignmentRoleEnum;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreUserAssignment;

class StorePermissionService
{
    /**
     * Check if user has permission for a specific action in a store.
     */
    public function hasPermission(User $user, string $storeId, string $permission): bool
    {
        // Admin sistem has all permissions
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        if (!$assignment) {
            return false;
        }

        return $this->roleHasPermission($assignment->assignment_role, $permission);
    }

    /**
     * Get user's assignment for a specific store.
     */
    public function getUserStoreAssignment(User $user, string $storeId): ?StoreUserAssignment
    {
        return $user->storeAssignments()
            ->where('store_id', $storeId)
            ->first();
    }

    /**
     * Check if a role has a specific permission.
     */
    public function roleHasPermission(AssignmentRoleEnum $role, string $permission): bool
    {
        $permissions = $this->getRolePermissions($role);
        
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Get all permissions for a role.
     */
    public function getRolePermissions(AssignmentRoleEnum $role): array
    {
        return match ($role) {
            AssignmentRoleEnum::OWNER => ['*'], // All permissions
            AssignmentRoleEnum::ADMIN => [
                'products.*',
                'orders.*',
                'inventory.*',
                'reports.*',
                'staff.view',
                'staff.create',
                'staff.update',
                'members.*',
                'tables.*',
                'categories.*',
                'discounts.*',
                'payments.*',
            ],
            AssignmentRoleEnum::MANAGER => [
                'products.view',
                'products.create',
                'products.update',
                'orders.*',
                'inventory.view',
                'inventory.update',
                'reports.view',
                'staff.view',
                'members.*',
                'tables.*',
                'categories.view',
                'discounts.view',
                'payments.view',
                'payments.create',
            ],
            AssignmentRoleEnum::STAFF => [
                'products.view',
                'orders.view',
                'orders.create',
                'orders.update',
                'inventory.view',
                'members.view',
                'members.create',
                'tables.view',
                'tables.update',
                'payments.view',
                'payments.create',
            ],
        };
    }

    /**
     * Get all stores where user has a specific permission.
     */
    public function getStoresWithPermission(User $user, string $permission): array
    {
        if ($user->hasRole('admin_sistem')) {
            return Store::pluck('id')->toArray();
        }

        return $user->storeAssignments()
            ->get()
            ->filter(function ($assignment) use ($permission) {
                return $this->roleHasPermission($assignment->assignment_role, $permission);
            })
            ->pluck('store_id')
            ->toArray();
    }

    /**
     * Check if user can manage another user in a store.
     */
    public function canManageUser(User $manager, User $targetUser, string $storeId): bool
    {
        if ($manager->hasRole('admin_sistem')) {
            return true;
        }

        $managerAssignment = $this->getUserStoreAssignment($manager, $storeId);
        $targetAssignment = $this->getUserStoreAssignment($targetUser, $storeId);

        if (!$managerAssignment || !$targetAssignment) {
            return false;
        }

        return $managerAssignment->canManage($targetAssignment);
    }

    /**
     * Get user's highest role in a store.
     */
    public function getUserHighestRole(User $user, string $storeId): ?AssignmentRoleEnum
    {
        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->assignment_role;
    }

    /**
     * Check if user has management access to a store.
     */
    public function hasManagementAccess(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->hasManagementPermissions() ?? false;
    }

    /**
     * Check if user has admin access to a store.
     */
    public function hasAdminAccess(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->hasAdminPermissions() ?? false;
    }

    /**
     * Check if user owns a store.
     */
    public function ownsStore(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->hasOwnerPermissions() ?? false;
    }
}