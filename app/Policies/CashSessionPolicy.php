<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;
use App\Enums\AssignmentRoleEnum;

class CashSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('cash_sessions.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CashSession $cashSession): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('cash_sessions.view') && 
               $user->stores()->where('stores.id', $cashSession->store_id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('cash_sessions.open');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CashSession $cashSession): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('cash_sessions.manage') && 
               $user->stores()->where('stores.id', $cashSession->store_id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CashSession $cashSession): bool
    {
        // Cash sessions should not be deleted to maintain audit trail
        return false;
    }

    /**
     * Determine whether the user can close a cash session.
     */
    public function close(User $user, CashSession $cashSession): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if ($user->hasRole('owner') || $user->storeAssignments()->where('assignment_role', AssignmentRoleEnum::OWNER->value)->exists()) return true;
        return $user->hasPermissionTo('cash_sessions.close') && 
               $user->stores()->where('stores.id', $cashSession->store_id)->exists();
    }
}

