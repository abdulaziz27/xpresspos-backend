<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\CashSession;
use App\Models\Discount;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\Refund;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\User;
use App\Models\Voucher;
use App\Policies\CategoryPolicy;
use App\Policies\CashSessionPolicy;
use App\Policies\DiscountPolicy;
use App\Policies\InventoryAdjustmentPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\InventoryTransferPolicy;
use App\Policies\MemberPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PromotionPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RecipePolicy;
use App\Policies\RefundPolicy;
use App\Policies\StorePolicy;
use App\Policies\StoreUserAssignmentPolicy;
use App\Policies\StoreUserPermissionPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TablePolicy;
use App\Policies\UserPolicy;
use App\Policies\VoucherPolicy;
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
        CashSession::class => CashSessionPolicy::class,
        Discount::class => DiscountPolicy::class,
        InventoryAdjustment::class => InventoryAdjustmentPolicy::class,
        InventoryItem::class => InventoryItemPolicy::class,
        InventoryTransfer::class => InventoryTransferPolicy::class,
        Member::class => MemberPolicy::class,
        Order::class => OrderPolicy::class,
        Payment::class => PaymentPolicy::class,
        Product::class => ProductPolicy::class,
        Promotion::class => PromotionPolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        Recipe::class => RecipePolicy::class,
        Refund::class => RefundPolicy::class,
        Store::class => StorePolicy::class,
        StoreUserAssignment::class => StoreUserPermissionPolicy::class,
        Supplier::class => SupplierPolicy::class,
        Table::class => TablePolicy::class,
        User::class => UserPolicy::class,
        Voucher::class => VoucherPolicy::class,
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
