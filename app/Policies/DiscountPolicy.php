<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

class DiscountPolicy
{
    /**
     * Determine whether the user can view any discounts.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        return $user->can('discounts.view');
    }

    /**
     * Determine whether the user can view the discount.
     */
    public function view(User $user, Discount $discount): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        return $user->can('discounts.view') && $user->store_id === $discount->store_id;
    }

    /**
     * Determine whether the user can create discounts.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        return $user->can('discounts.create');
    }

    /**
     * Determine whether the user can update the discount.
     */
    public function update(User $user, Discount $discount): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        return $user->can('discounts.update') && $user->store_id === $discount->store_id;
    }

    /**
     * Determine whether the user can delete the discount.
     */
    public function delete(User $user, Discount $discount): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        return $user->can('discounts.delete') && $user->store_id === $discount->store_id;
    }
}
