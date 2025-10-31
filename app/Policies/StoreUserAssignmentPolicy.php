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
        if ($user->hasRole('admin_sistem')) return true;
        // now rely on permission, user must have permission to manage assignments in store context
        return $user->can('store_assignments.viewAny');
    }

    /**
     * Determine whether the user can view the assignment.
     */
    public function view(User $user, StoreUserAssignment $assignment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->id === $assignment->user_id) return true;
        // For others, permission check
        return $user->can('store_assignments.view') && ($assignment->store_id === $user->store_id);
    }

    /**
     * Determine whether the user can create assignments.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        // now permission-based, not role-based
        return $user->can('store_assignments.create');
    }

    /**
     * Determine whether the user can update the assignment.
     */
    public function update(User $user, StoreUserAssignment $assignment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        // permission plus only if can manage assignment in this store
        return $user->can('store_assignments.update') && ($assignment->store_id === $user->store_id);
    }

    /**
     * Determine whether the user can delete the assignment.
     */
    public function delete(User $user, StoreUserAssignment $assignment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        // prevent self-delete of main assignment
        if ($user->id === $assignment->user_id && $assignment->is_primary) return false;
        return $user->can('store_assignments.delete') && ($assignment->store_id === $user->store_id);
    }

    /**
     * Determine whether the user can assign users to a specific store.
     */
    public function assignToStore(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        return $user->can('store_assignments.assign') && ($user->store_id === $storeId);
    }

    /**
     * Determine whether the user can set primary store for another user.
     */
    public function setPrimaryStore(User $user, User $targetUser): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->id === $targetUser->id) return true;
        // permission-based with intersection on stores
        return $user->can('store_assignments.set_primary');
    }
}