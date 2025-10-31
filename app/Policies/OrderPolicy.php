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
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->can('orders.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($order->store_id)) return true;
        return $user->can('orders.view') && $user->store_id === $order->store_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOfStore') && $user->isOwnerOfStore()) return true;
        return $user->can('orders.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($order->store_id)) return true;
        return $user->can('orders.update') && $user->store_id === $order->store_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($order->store_id)) return true;
        return $user->can('orders.delete') && $user->store_id === $order->store_id;
    }

    /**
     * Determine whether the user can process refunds.
     */
    public function refund(User $user, Order $order): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($order->store_id)) return true;
        return $user->can('orders.refund') && $user->store_id === $order->store_id;
    }

    /**
     * Determine whether the user can void orders.
     */
    public function void(User $user, Order $order): bool
    {
        if ($user->hasRole('admin_sistem')) return true;
        if (method_exists($user, 'isOwnerOf') && $user->isOwnerOf($order->store_id)) return true;
        return $user->can('orders.void') && $user->store_id === $order->store_id;
    }
}