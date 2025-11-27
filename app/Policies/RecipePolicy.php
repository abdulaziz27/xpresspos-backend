<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class RecipePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('products.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Recipe $recipe): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('products.view') && 
               $user->currentTenant()?->id === $recipe->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('products.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Recipe $recipe): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('products.update') && 
               $user->currentTenant()?->id === $recipe->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Recipe $recipe): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('products.delete') && 
               $user->currentTenant()?->id === $recipe->tenant_id;
    }
}

