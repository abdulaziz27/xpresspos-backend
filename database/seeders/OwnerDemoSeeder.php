<?php

namespace Database\Seeders;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\CogsHistory;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Member;
use App\Models\MemberTier;
use App\Services\LoyaltyService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = config('demo.store_id');

        /** @var Store|null $store */
        $store = $storeId ? Store::find($storeId) : null;

        if (!$store) {
            $store = Store::first();
        }

        if (!$store) {
            $store = Store::create([
                'name' => 'Demo Coffee Shop',
                'email' => 'demo-owner@posxpress.com',
                'phone' => '+628123456789',
                'address' => 'Jl. Demo Raya No. 1',
                'settings' => [
                    'currency' => 'IDR',
                    'tax_rate' => 10,
                    'service_charge_rate' => 5,
                ],
                'status' => 'active',
            ]);
        }

        config(['demo.store_id' => $store->id]);

        // Ensure owner assignment exists
        $owner = User::where('email', 'aziz@xpress.com')->first();
        if (!$owner) {
            $owner = User::create([
                'name' => 'Abdul Aziz',
                'email' => 'aziz@xpress.com',
                'password' => bcrypt('password'),
                'store_id' => $store->id,
            ]);
            $owner->assignRole('owner');
        }
        if ($owner) {
            StoreUserAssignment::updateOrCreate(
                ['store_id' => $store->id, 'user_id' => $owner->id],
                ['assignment_role' => 'owner', 'is_primary' => true]
            );
        }

        // Pick a manager and cashier for sample data
        $manager = User::role('manager')->where('store_id', $store->id)->first();
        if (!$manager) {
            $manager = User::create([
                'name' => 'Store Manager',
                'email' => 'manager@posxpress.com',
                'password' => bcrypt('password'),
                'store_id' => $store->id,
            ]);
            $manager->assignRole('manager');
        }
        StoreUserAssignment::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $manager->id],
            ['assignment_role' => 'manager', 'is_primary' => false]
        );

        $cashier = User::role('cashier')->where('store_id', $store->id)->first();
        if (!$cashier) {
            $cashier = User::create([
                'name' => 'Demo Cashier',
                'email' => 'cashier@posxpress.com',
                'password' => bcrypt('password'),
                'store_id' => $store->id,
            ]);
            $cashier->assignRole('cashier');
        }
        StoreUserAssignment::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $cashier->id],
            ['assignment_role' => 'cashier', 'is_primary' => false]
        );

        // Ensure default tiers exist
        /** @var LoyaltyService $loyaltyService */
        $loyaltyService = app(LoyaltyService::class);
        if (MemberTier::withoutStoreScope()->where('store_id', $store->id)->count() === 0) {
            $loyaltyService->initializeDefaultTiers($store->id);
        }

        $silverTier = MemberTier::withoutStoreScope()
            ->where('store_id', $store->id)
            ->where('slug', 'silver')
            ->first()
            ?? MemberTier::withoutStoreScope()->where('store_id', $store->id)->ordered()->first();

        $member = Member::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'email' => 'customer@example.com'],
            [
                'member_number' => 'MBR000001',
                'name' => 'Sample Customer',
                'phone' => '+6281234567890',
                'loyalty_points' => 120,
                'total_spent' => 150000,
                'visit_count' => 8,
                'tier_id' => $silverTier->id,
                'is_active' => true,
                'last_visit_at' => now()->subDays(2),
            ]
        );

        // Category & Product
        $category = Category::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'slug' => 'coffee'],
            [
                'name' => 'Coffee',
                'description' => 'Signature coffee drinks',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $product = Product::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'sku' => 'ESP-001'],
            [
                'category_id' => $category->id,
                'name' => 'Espresso',
                'description' => 'Single shot espresso',
                'price' => 25000,
                'cost_price' => 8000,
                'track_inventory' => true,
                'stock' => 120,
                'min_stock_level' => 20,
                'status' => true,
                'sort_order' => 1,
            ]
        );

        // Recipe and ingredients
        $recipe = Recipe::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $product->id],
            [
                'name' => 'Espresso Shot',
                'description' => 'Standard espresso recipe',
                'yield_quantity' => 1,
                'yield_unit' => 'cup',
                'total_cost' => 5000,
                'cost_per_unit' => 5000,
                'is_active' => true,
            ]
        );

        $recipe->items()->updateOrCreate(
            ['store_id' => $store->id, 'ingredient_product_id' => $product->id],
            [
                'store_id' => $store->id,
                'quantity' => 0.02,
                'unit' => 'kg',
                'unit_cost' => 5000,
                'total_cost' => 5000,
            ]
        );

        // Cash session
        $cashSession = CashSession::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'opened_at' => now()->startOfDay()],
            [
                'user_id' => $cashier?->id ?? $owner?->id,
                'opening_balance' => 300000,
                'cash_sales' => 450000,
                'cash_expenses' => 50000,
                'expected_balance' => 700000,
                'status' => 'open',
                'notes' => 'Morning shift session',
            ]
        );

        // Expense
        Expense::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'description' => 'Milk restock'],
            [
                'cash_session_id' => $cashSession->id,
                'user_id' => $manager?->id ?? $owner?->id,
                'category' => 'Supplies',
                'amount' => 50000,
                'vendor' => 'Local Dairy',
                'expense_date' => now()->toDateString(),
                'notes' => 'Purchased fresh milk for espresso/beverage prep',
            ]
        );

        // Inventory movement
        InventoryMovement::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'reference_id' => 'restock-001'],
            [
                'product_id' => $product->id,
                'user_id' => $manager?->id ?? $owner?->id,
                'type' => 'purchase',
                'quantity' => 50,
                'unit_cost' => 6000,
                'total_cost' => 300000,
                'reason' => 'Restock beans',
                'reference_type' => 'purchase_order',
                'notes' => 'Restocked coffee beans from supplier',
            ]
        );

        // Order & Order item
        $order = Order::withoutGlobalScopes()->updateOrCreate(
            ['store_id' => $store->id, 'order_number' => 'ORD-' . now()->format('Ymd') . '-001'],
            [
                'user_id' => $cashier?->id ?? $owner?->id,
                'member_id' => $member->id,
                'status' => 'completed',
                'subtotal' => 25000,
                'tax_amount' => 2500,
                'discount_amount' => 0,
                'service_charge' => 1500,
                'total_amount' => 29000,
                'total_items' => 1,
                'notes' => 'Morning order',
                'completed_at' => now()->subHour(),
            ]
        );

        OrderItem::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'order_id' => $order->id, 'product_id' => $product->id],
            [
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => 1,
                'unit_price' => 25000,
                'notes' => 'Less sugar',
            ]
        );

        // Payment
        Payment::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'order_id' => $order->id],
            [
                'payment_method' => 'cash',
                'amount' => 29000,
                'status' => 'completed',
                'processed_at' => now()->subHour(),
                'notes' => 'Paid in cash',
            ]
        );

        // COGS history
        CogsHistory::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'order_id' => $order->id, 'product_id' => $product->id],
            [
                'quantity_sold' => 1,
                'unit_cost' => 8000,
                'total_cogs' => 8000,
                'calculation_method' => 'weighted_average',
                'cost_breakdown' => [
                    'ingredients' => [
                        ['name' => 'Espresso Beans', 'quantity' => 20, 'unit' => 'gram', 'cost' => 5000],
                        ['name' => 'Water', 'quantity' => 30, 'unit' => 'ml', 'cost' => 0],
                    ],
                ],
            ]
        );

        $this->command?->info('Owner demo data seeded for store: ' . $store->name);
    }
}
