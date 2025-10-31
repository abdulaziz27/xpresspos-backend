<?php

namespace App\Policies;

use App\Models\Table;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TablePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->hasPermissionTo('tables.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Table $table): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($table->store_id)) return true;
        return $user->hasPermissionTo('tables.view') && $user->store_id === $table->store_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->hasPermissionTo('tables.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Table $table): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($table->store_id)) return true;
        return $user->hasPermissionTo('tables.update') && $user->store_id === $table->store_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Table $table): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($table->store_id)) return true;
        return $user->hasPermissionTo('tables.delete') && $user->store_id === $table->store_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Table $table): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($table->store_id)) return true;
        return $user->hasPermissionTo('tables.delete') && $user->store_id === $table->store_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Table $table): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($table->store_id)) return true;
        return $user->hasPermissionTo('tables.delete') && $user->store_id === $table->store_id;
    }
}