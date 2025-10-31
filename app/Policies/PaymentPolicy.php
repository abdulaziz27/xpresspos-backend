<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->hasPermissionTo('payments.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($payment->store_id)) return true;
        return $user->hasPermissionTo('payments.view') && 
               $user->store_id === $payment->store_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->hasPermissionTo('payments.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($payment->store_id)) return true;
        return $user->hasPermissionTo('payments.update') && 
               $user->store_id === $payment->store_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($payment->store_id)) return true;
        return $user->hasPermissionTo('payments.delete') && 
               $user->store_id === $payment->store_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($payment->store_id)) return true;
        return $user->hasPermissionTo('payments.delete') && 
               $user->store_id === $payment->store_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($payment->store_id)) return true;
        return $user->hasPermissionTo('payments.delete') && 
               $user->store_id === $payment->store_id;
    }
}