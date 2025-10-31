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
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->can('products.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($category->store_id)) return true;
        return $user->can('products.view') && $user->store_id === $category->store_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->can('products.manage_categories');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($category->store_id)) return true;
        return $user->can('products.manage_categories') && $user->store_id === $category->store_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($category->store_id)) return true;
        return $user->can('products.manage_categories') && $user->store_id === $category->store_id;
    }
}
