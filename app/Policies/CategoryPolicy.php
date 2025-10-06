<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // System admin + store operational roles can view categories
        if ($user->hasAnyRole(['admin_sistem', 'owner', 'manager', 'cashier'])) {
            return true;
        }

        return $user->hasPermissionTo('products.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        // System admin can view any category
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can view categories in their store
        if ($user->can('products.view') && $user->store_id === $category->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // System admin can create categories in any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users with category management permission can create categories
        return $user->can('products.manage_categories');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        // System admin can update any category
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can update categories in their store
        if ($user->can('products.manage_categories') && $user->store_id === $category->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        // System admin can delete any category
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can delete categories in their store
        if ($user->can('products.manage_categories') && $user->store_id === $category->store_id) {
            return true;
        }

        return false;
    }
}
