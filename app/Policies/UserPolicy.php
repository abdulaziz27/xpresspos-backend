<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // System admin can view all users globally
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Store owners and managers can view users in their store
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // System admin can view any user
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can view themselves
        if ($user->id === $model->id) {
            return true;
        }

        // Store owners and managers can view users in their store
        if ($user->can('users.view') && $user->store_id === $model->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // System admin can create users in any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Store owners can create users in their store
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // System admin can update any user
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can update themselves (limited fields)
        if ($user->id === $model->id) {
            return true;
        }

        // Store owners can update users in their store
        if ($user->can('users.update') && $user->store_id === $model->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // System admin can delete any user (except themselves)
        if ($user->hasRole('admin_sistem') && $user->id !== $model->id) {
            return true;
        }

        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Store owners can delete users in their store (except system admins)
        if ($user->can('users.delete') && 
            $user->store_id === $model->store_id && 
            !$model->hasRole('admin_sistem')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage roles for the model.
     */
    public function manageRoles(User $user, User $model): bool
    {
        // System admin can manage any user's roles
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Store owners can manage roles for users in their store (except system admins)
        if ($user->can('users.manage_roles') && 
            $user->store_id === $model->store_id && 
            !$model->hasRole('admin_sistem')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign a specific role.
     */
    public function assignRole(User $user, string $roleName): bool
    {
        // System admin can assign any role
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Store owners cannot assign system admin role
        if ($roleName === 'admin_sistem') {
            return false;
        }

        // Store owners can assign other roles if they have permission
        return $user->can('users.manage_roles');
    }
}