<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // System admin can view all orders globally
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // All authenticated users can view orders in their store
        return $user->can('orders.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        // System admin can view any order
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can view orders in their store
        if ($user->can('orders.view') && $user->store_id === $order->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // System admin can create orders in any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // All staff can create orders
        return $user->can('orders.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        // System admin can update any order
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users can update orders in their store
        if ($user->can('orders.update') && $user->store_id === $order->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        // System admin can delete any order
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Only owners and managers can delete orders
        if ($user->can('orders.delete') && $user->store_id === $order->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can process refunds.
     */
    public function refund(User $user, Order $order): bool
    {
        // System admin can refund any order
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Users with refund permission can refund orders in their store
        if ($user->can('orders.refund') && $user->store_id === $order->store_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can void orders.
     */
    public function void(User $user, Order $order): bool
    {
        // System admin can void any order
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Only owners and managers can void orders
        if ($user->can('orders.void') && $user->store_id === $order->store_id) {
            return true;
        }

        return false;
    }
}