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
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->can('products.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($product->store_id)) return true;
        return $user->can('products.view') && $user->store_id === $product->store_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->can('products.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($product->store_id)) return true;
        return $user->can('products.update') && $user->store_id === $product->store_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($product->store_id)) return true;
        return $user->can('products.delete') && $user->store_id === $product->store_id;
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
