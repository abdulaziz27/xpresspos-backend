<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('expenses.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Expense $expense): bool
    {
        if (!$user->hasPermissionTo('expenses.view')) {
            return false;
        }

        // Check tenant ownership
        if ($expense->store->tenant_id !== $user->currentTenant()->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('expenses.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense): bool
    {
        if (!$user->hasPermissionTo('expenses.update')) {
            return false;
        }

        // Check tenant ownership
        if ($expense->store->tenant_id !== $user->currentTenant()->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense): bool
    {
        if (!$user->hasPermissionTo('expenses.delete')) {
            return false;
        }

        // Check tenant ownership
        if ($expense->store->tenant_id !== $user->currentTenant()->id) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Expense $expense): bool
    {
        // Expenses usually don't use soft deletes, but if they do:
        return $user->hasRole('owner');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->hasRole('owner');
    }
}
