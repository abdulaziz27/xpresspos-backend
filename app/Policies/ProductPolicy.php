<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // System admin + operational roles can always view product listings
        if ($user->hasAnyRole(['admin_sistem', 'owner', 'manager', 'cashier'])) {
            return true;
        }

        return $user->hasPermissionTo('products.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        // System admin can view any product
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can view products in their store
        if ($user->can('products.view') && $user->store_id === $product->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // System admin can create products in any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Store owners and managers can create products
        return $user->can('products.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        // System admin can update any product
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can update products in their store
        if ($user->can('products.update') && $user->store_id === $product->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        // System admin can delete any product
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can delete products in their store
        if ($user->can('products.delete') && $user->store_id === $product->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage categories.
     */
    public function manageCategories(User $user): bool
    {
        // System admin can manage categories in any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Store owners and managers can manage categories
        return $user->can('products.manage_categories');
    }
}
