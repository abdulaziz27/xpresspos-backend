<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\StoreUserAssignment;
use App\Models\Table;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\DiscountPolicy;
use App\Policies\MemberPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RefundPolicy;
use App\Policies\StoreUserAssignmentPolicy;
use App\Policies\StoreUserPermissionPolicy;
use App\Policies\TablePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Category::class => CategoryPolicy::class,
        Discount::class => DiscountPolicy::class,
        Member::class => MemberPolicy::class,
        Order::class => OrderPolicy::class,
        Payment::class => PaymentPolicy::class,
        Product::class => ProductPolicy::class,
        Refund::class => RefundPolicy::class,
        StoreUserAssignment::class => StoreUserPermissionPolicy::class,
        Table::class => TablePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Allow store Owner full access within the current team/store context.
        // Spatie team context (set via middleware) ensures this only applies to the active store.
        Gate::before(function ($user, string $ability = null) {
            try {
                // Global override:
                // - admin_sistem: full access system-wide
                // - owner: full access within active team/store context (team already set by middleware)
                if ($user->hasRole('admin_sistem')) {
                    return true;
                }

                // Treat owner as superuser across any team if they have owner role
                // OR have an owner assignment in any store. This avoids Livewire requests
                // that may miss team context.
                $isOwnerAnyTeam = $user->roles()->where('name', 'owner')->exists();
                $hasOwnerAssignment = method_exists($user, 'storeAssignments')
                    ? $user->storeAssignments()->where('assignment_role', 'owner')->exists()
                    : false;

                return ($isOwnerAnyTeam || $hasOwnerAssignment) ? true : null;
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
