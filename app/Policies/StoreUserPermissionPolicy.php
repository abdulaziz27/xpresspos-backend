<?php

namespace App\Policies;

use App\Models\StoreUserAssignment;
use App\Models\User;
use App\Services\StoreContext;
use App\Services\StorePermissionService;

class StoreUserPermissionPolicy
{
    protected $storeContext;
    protected $permissionService;

    public function __construct(StoreContext $storeContext, StorePermissionService $permissionService)
    {
        $this->storeContext = $storeContext;
        $this->permissionService = $permissionService;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin sistem can view all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can view assignments in their store
        $currentStoreId = $this->storeContext->current($user);
        return $currentStoreId && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StoreUserAssignment $storeUserAssignment): bool
    {
        // Admin sistem can view all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can only view assignments in their store
        $currentStoreId = $this->storeContext->current($user);
        
        return $currentStoreId 
            && $storeUserAssignment->store_id === $currentStoreId
            && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin sistem can create anywhere
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can create assignments in their store
        $currentStoreId = $this->storeContext->current($user);
        return $currentStoreId && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StoreUserAssignment $storeUserAssignment): bool
    {
        // Admin sistem can update all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can only update assignments in their store
        $currentStoreId = $this->storeContext->current($user);
        
        // Cannot update other owners
        if ($storeUserAssignment->assignment_role->value === 'owner') {
            return false;
        }
        
        return $currentStoreId 
            && $storeUserAssignment->store_id === $currentStoreId
            && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StoreUserAssignment $storeUserAssignment): bool
    {
        // Admin sistem can delete all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can only delete assignments in their store
        $currentStoreId = $this->storeContext->current($user);
        
        // Cannot delete other owners
        if ($storeUserAssignment->assignment_role->value === 'owner') {
            return false;
        }
        
        return $currentStoreId 
            && $storeUserAssignment->store_id === $currentStoreId
            && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can manage permissions for the model.
     */
    public function managePermissions(User $user, StoreUserAssignment $storeUserAssignment): bool
    {
        // Admin sistem can manage all permissions
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can manage permissions in their store
        $currentStoreId = $this->storeContext->current($user);
        
        // Cannot manage permissions of other owners
        if ($storeUserAssignment->assignment_role->value === 'owner') {
            return false;
        }
        
        return $currentStoreId 
            && $storeUserAssignment->store_id === $currentStoreId
            && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can grant specific permission.
     */
    public function grantPermission(User $user, StoreUserAssignment $storeUserAssignment, string $permission): bool
    {
        // Admin sistem can grant any permission
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can grant permissions they have
        $currentStoreId = $this->storeContext->current($user);
        
        if (!$currentStoreId || $storeUserAssignment->store_id !== $currentStoreId) {
            return false;
        }

        // Cannot grant permissions to other owners
        if ($storeUserAssignment->assignment_role->value === 'owner') {
            return false;
        }

        // Owner must have the permission they're trying to grant
        return $this->permissionService->hasPermission($user, $currentStoreId, $permission);
    }

    /**
     * Determine whether the user can bulk update assignments.
     */
    public function bulkUpdate(User $user): bool
    {
        // Admin sistem can bulk update
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can bulk update in their store
        $currentStoreId = $this->storeContext->current($user);
        return $currentStoreId && $this->permissionService->ownsStore($user, $currentStoreId);
    }

    /**
     * Determine whether the user can view audit trail.
     */
    public function viewAuditTrail(User $user, StoreUserAssignment $storeUserAssignment): bool
    {
        // Admin sistem can view all audit trails
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Owner can view audit trail in their store
        $currentStoreId = $this->storeContext->current($user);
        
        return $currentStoreId 
            && $storeUserAssignment->store_id === $currentStoreId
            && $this->permissionService->ownsStore($user, $currentStoreId);
    }
}