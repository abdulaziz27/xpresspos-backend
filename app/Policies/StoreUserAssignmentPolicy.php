<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StoreUserAssignment;
use App\Services\StorePermissionService;

class StoreUserAssignmentPolicy
{
    public function __construct(
        private readonly StorePermissionService $permissionService
    ) {
    }

    /**
     * Determine whether the user can view any assignments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_sistem') || 
               $user->storeAssignments()->exists();
    }

    /**
     * Determine whether the user can view the assignment.
     */
    public function view(User $user, StoreUserAssignment $assignment): bool
    {
        // Admin sistem can view all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can view their own assignments
        if ($user->id === $assignment->user_id) {
            return true;
        }

        // Users with management permissions can view assignments in their stores
        return $this->permissionService->hasManagementAccess($user, $assignment->store_id);
    }

    /**
     * Determine whether the user can create assignments.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin_sistem') || 
               $user->storeAssignments()->where('assignment_role', 'owner')->exists() ||
               $user->storeAssignments()->where('assignment_role', 'admin')->exists();
    }

    /**
     * Determine whether the user can update the assignment.
     */
    public function update(User $user, StoreUserAssignment $assignment): bool
    {
        // Admin sistem can update all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Check if user has admin access to the store
        if (!$this->permissionService->hasAdminAccess($user, $assignment->store_id)) {
            return false;
        }

        // Get user's assignment in this store
        $userAssignment = $this->permissionService->getUserStoreAssignment($user, $assignment->store_id);
        
        if (!$userAssignment) {
            return false;
        }

        // Users can only manage assignments with lower hierarchy
        return $userAssignment->canManage($assignment);
    }

    /**
     * Determine whether the user can delete the assignment.
     */
    public function delete(User $user, StoreUserAssignment $assignment): bool
    {
        // Admin sistem can delete all
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users cannot delete their own primary assignment
        if ($user->id === $assignment->user_id && $assignment->is_primary) {
            return false;
        }

        // Check if user has admin access to the store
        if (!$this->permissionService->hasAdminAccess($user, $assignment->store_id)) {
            return false;
        }

        // Get user's assignment in this store
        $userAssignment = $this->permissionService->getUserStoreAssignment($user, $assignment->store_id);
        
        if (!$userAssignment) {
            return false;
        }

        // Users can only delete assignments with lower hierarchy
        return $userAssignment->canManage($assignment);
    }

    /**
     * Determine whether the user can assign users to a specific store.
     */
    public function assignToStore(User $user, string $storeId): bool
    {
        // Admin sistem can assign to any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Check if user has admin access to the store
        return $this->permissionService->hasAdminAccess($user, $storeId);
    }

    /**
     * Determine whether the user can set primary store for another user.
     */
    public function setPrimaryStore(User $user, User $targetUser): bool
    {
        // Admin sistem can set for anyone
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can set their own primary store
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Check if user has management permissions in any shared stores
        $userStores = $user->storeAssignments()
            ->where('assignment_role', 'owner')
            ->orWhere('assignment_role', 'admin')
            ->pluck('store_id');

        $targetStores = $targetUser->storeAssignments()
            ->pluck('store_id');

        return $userStores->intersect($targetStores)->isNotEmpty();
    }
}