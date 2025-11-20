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
use App\Models\StaffPerformance;
use App\Models\Table;
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
                'name' => 'Arasta Coffee - Central',
                'email' => 'store@xpresspos.id',
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

        // Ensure owner assignment exists (will use owner@xpresspos.id from FilamentUserSeeder)
        $owner = User::where('email', 'owner@xpresspos.id')->first();
        
        if (!$owner) {
            $owner = User::firstOrCreate(
                ['email' => 'owner@xpresspos.id'],
                [
                    'name' => 'Store Owner',
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // CRITICAL: Create store_user_assignment for owner
        \App\Models\StoreUserAssignment::updateOrCreate(
            [
                'store_id' => $store->id,
                'user_id' => $owner->id,
            ],
            [
                'assignment_role' => 'owner',
                'is_primary' => true,
            ]
        );
        $this->command->info("Created store_user_assignment for {$owner->email} to {$store->id}");
        
        // Assign role with team context
        $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
            ->where('store_id', $store->id)
            ->first();
        
        if ($ownerRole) {
            // CRITICAL: Always set team context BEFORE any role operation
            setPermissionsTeamId($store->id);
            
            // Force remove any existing role assignments for this user in this store
            $owner->roles()->wherePivot('store_id', $store->id)->detach();
            
            // Assign role fresh
            $owner->assignRole($ownerRole);
            
            // Verify assignment
            $owner->refresh();
            setPermissionsTeamId($store->id);
            
            if (!$owner->hasRole('owner')) {
                $this->command->warn("⚠️ Failed to assign owner role to {$owner->email} for store {$store->name}");
            }
        } else {
            $this->command->warn("⚠️ Owner role not found for store {$store->name} (ID: {$store->id})");
        }
        
        if ($owner) {
            StoreUserAssignment::updateOrCreate(
                ['store_id' => $store->id, 'user_id' => $owner->id],
                ['assignment_role' => 'owner', 'is_primary' => true]
            );
        }

        // Pick a manager and cashier for sample data
        $manager = User::where('store_id', $store->id)
            ->whereHas('storeAssignments', function($q) use ($store) {
                $q->where('store_id', $store->id)->where('assignment_role', 'manager');
            })->first();
        
        if (!$manager) {
            $manager = User::firstOrCreate(
                ['email' => 'manager@xpresspos.id'],
                [
                    'name' => 'Store Manager',
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                ]
            );
            
            // Assign role with team context
            $managerRole = \Spatie\Permission\Models\Role::where('name', 'manager')
                ->where('store_id', $store->id)
                ->first();
            
            if ($managerRole) {
                // CRITICAL: Always set team context BEFORE any role operation
                setPermissionsTeamId($store->id);
                
                // Force remove any existing role assignments for this user in this store
                $manager->roles()->wherePivot('store_id', $store->id)->detach();
                
                // Assign role fresh
                $manager->assignRole($managerRole);
                
                // Verify assignment
                $manager->refresh();
                setPermissionsTeamId($store->id);
            }
        }
        StoreUserAssignment::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $manager->id],
            ['assignment_role' => 'manager', 'is_primary' => false]
        );

        $cashier = User::where('store_id', $store->id)
            ->whereHas('storeAssignments', function($q) use ($store) {
                $q->where('store_id', $store->id)->where('assignment_role', 'staff');
            })->first();
            
        if (!$cashier) {
            $cashier = User::firstOrCreate(
                ['email' => 'cashier@xpresspos.id'],
                [
                    'name' => 'Store Cashier',
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                ]
            );
            
            // Assign role with team context
            $cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')
                ->where('store_id', $store->id)
                ->first();
            
            if ($cashierRole) {
                // CRITICAL: Always set team context BEFORE any role operation
                setPermissionsTeamId($store->id);
                
                // Force remove any existing role assignments for this user in this store
                $cashier->roles()->wherePivot('store_id', $store->id)->detach();
                
                // Assign role fresh
                $cashier->assignRole($cashierRole);
                
                // Verify assignment
                $cashier->refresh();
                setPermissionsTeamId($store->id);
            }
        }
        StoreUserAssignment::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $cashier->id],
            ['assignment_role' => 'staff', 'is_primary' => false]
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

        // Categories
        $categories = [
            ['slug' => 'coffee', 'name' => 'Coffee', 'description' => 'Signature coffee drinks', 'sort' => 1],
            ['slug' => 'non-coffee', 'name' => 'Non Coffee', 'description' => 'Tea, chocolate, and more', 'sort' => 2],
            ['slug' => 'food', 'name' => 'Food', 'description' => 'Pastries & light bites', 'sort' => 3],
            ['slug' => 'bottle', 'name' => 'Bottle', 'description' => 'Ready to drink bottles', 'sort' => 4],
        ];
        $categoryMap = [];
        foreach ($categories as $cat) {
            $c = Category::withoutStoreScope()->updateOrCreate(
                ['store_id' => $store->id, 'slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'status' => true,
                    'sort_order' => $cat['sort'],
                ]
            );
            $categoryMap[$cat['slug']] = $c;
        }

        // Products (some intentionally low stock)
        $productsData = [
            ['sku' => 'ESP-001', 'name' => 'Espresso', 'cat' => 'coffee', 'price' => 25000, 'cost' => 8000, 'stock' => 120, 'min' => 20],
            ['sku' => 'LAT-001', 'name' => 'Cafe Latte', 'cat' => 'coffee', 'price' => 35000, 'cost' => 12000, 'stock' => 60, 'min' => 25],
            ['sku' => 'CAP-001', 'name' => 'Cappuccino', 'cat' => 'coffee', 'price' => 34000, 'cost' => 11000, 'stock' => 18, 'min' => 20], // low stock
            ['sku' => 'AME-001', 'name' => 'Americano', 'cat' => 'coffee', 'price' => 28000, 'cost' => 7000, 'stock' => 45, 'min' => 15],
            ['sku' => 'MOC-001', 'name' => 'Mocha', 'cat' => 'coffee', 'price' => 38000, 'cost' => 14000, 'stock' => 22, 'min' => 20],
            ['sku' => 'TEA-001', 'name' => 'Lemon Tea', 'cat' => 'non-coffee', 'price' => 24000, 'cost' => 6000, 'stock' => 35, 'min' => 10],
            ['sku' => 'CHO-001', 'name' => 'Hot Chocolate', 'cat' => 'non-coffee', 'price' => 30000, 'cost' => 10000, 'stock' => 12, 'min' => 15], // low stock
            ['sku' => 'CRT-001', 'name' => 'Croissant', 'cat' => 'food', 'price' => 22000, 'cost' => 9000, 'stock' => 30, 'min' => 10],
            ['sku' => 'BAG-001', 'name' => 'Bagel', 'cat' => 'food', 'price' => 20000, 'cost' => 8000, 'stock' => 8, 'min' => 12], // low stock
            ['sku' => 'BCL-001', 'name' => 'Bottled Cold Latte', 'cat' => 'bottle', 'price' => 42000, 'cost' => 18000, 'stock' => 20, 'min' => 10],
        ];
        $products = [];
        foreach ($productsData as $pd) {
            $products[$pd['sku']] = Product::withoutStoreScope()->updateOrCreate(
                ['store_id' => $store->id, 'sku' => $pd['sku']],
                [
                    'category_id' => $categoryMap[$pd['cat']]->id,
                    'name' => $pd['name'],
                    'description' => $pd['name'] . ' made fresh',
                    'price' => $pd['price'],
                    'cost_price' => $pd['cost'],
                    'track_inventory' => true,
                    'stock' => $pd['stock'],
                    'min_stock_level' => $pd['min'],
                    'status' => true,
                ]
            );
        }

        // Recipes (increase coverage across popular items)
        $recipeSpecs = [
            ['sku' => 'ESP-001', 'name' => 'Espresso Shot', 'unit' => 'cup', 'total_cost' => 5000, 'ingredients' => [
                ['ref' => 'ESP-001', 'qty' => 0.02, 'unit' => 'kg', 'unit_cost' => 5000],
            ]],
            ['sku' => 'LAT-001', 'name' => 'Cafe Latte Base', 'unit' => 'cup', 'total_cost' => 13000, 'ingredients' => [
                ['ref' => 'ESP-001', 'qty' => 1, 'unit' => 'shot', 'unit_cost' => 5000],
            ]],
            ['sku' => 'CAP-001', 'name' => 'Cappuccino Base', 'unit' => 'cup', 'total_cost' => 12000, 'ingredients' => [
                ['ref' => 'ESP-001', 'qty' => 1, 'unit' => 'shot', 'unit_cost' => 5000],
            ]],
            ['sku' => 'AME-001', 'name' => 'Americano Base', 'unit' => 'cup', 'total_cost' => 6000, 'ingredients' => [
                ['ref' => 'ESP-001', 'qty' => 1, 'unit' => 'shot', 'unit_cost' => 5000],
            ]],
            ['sku' => 'MOC-001', 'name' => 'Mocha Base', 'unit' => 'cup', 'total_cost' => 15000, 'ingredients' => [
                ['ref' => 'ESP-001', 'qty' => 1, 'unit' => 'shot', 'unit_cost' => 5000],
            ]],
            ['sku' => 'CHO-001', 'name' => 'Chocolate Drink Base', 'unit' => 'cup', 'total_cost' => 9000, 'ingredients' => [
                ['ref' => 'CHO-001', 'qty' => 1, 'unit' => 'portion', 'unit_cost' => 9000],
            ]],
            ['sku' => 'TEA-001', 'name' => 'Lemon Tea Base', 'unit' => 'cup', 'total_cost' => 4000, 'ingredients' => [
                ['ref' => 'TEA-001', 'qty' => 1, 'unit' => 'portion', 'unit_cost' => 4000],
            ]],
            ['sku' => 'CRT-001', 'name' => 'Croissant Prep', 'unit' => 'piece', 'total_cost' => 8000, 'ingredients' => [
                ['ref' => 'CRT-001', 'qty' => 1, 'unit' => 'piece', 'unit_cost' => 8000],
            ]],
        ];
        foreach ($recipeSpecs as $spec) {
            if (!isset($products[$spec['sku']])) continue;
            $prod = $products[$spec['sku']];
            $recipe = Recipe::withoutStoreScope()->updateOrCreate(
                ['store_id' => $store->id, 'product_id' => $prod->id],
                [
                    'name' => $spec['name'],
                    'description' => $spec['name'] . ' recipe',
                    'yield_quantity' => 1,
                    'yield_unit' => $spec['unit'],
                    'total_cost' => $spec['total_cost'],
                    'cost_per_unit' => $spec['total_cost'],
                    'is_active' => true,
                ]
            );
            foreach ($spec['ingredients'] as $ing) {
                if (!isset($products[$ing['ref']])) continue;
                $ingProduct = $products[$ing['ref']];
                $recipe->items()->updateOrCreate(
                    ['store_id' => $store->id, 'ingredient_product_id' => $ingProduct->id],
                    [
                        'store_id' => $store->id,
                        'quantity' => $ing['qty'],
                        'unit' => $ing['unit'],
                        'unit_cost' => $ing['unit_cost'],
                        'total_cost' => $ing['unit_cost'],
                    ]
                );
            }
        }

        // Cash sessions (morning & afternoon)
        $cashSession = CashSession::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'opened_at' => now()->startOfDay()],
            [
                'user_id' => $cashier?->id ?? $owner?->id,
                'opening_balance' => 300000,
                'cash_sales' => 0,
                'cash_expenses' => 0,
                'expected_balance' => 300000,
                'status' => 'open',
                'notes' => 'Morning shift session',
            ]
        );
        $afternoonSession = CashSession::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'opened_at' => now()->startOfDay()->addHours(8)],
            [
                'user_id' => $cashier?->id ?? $owner?->id,
                'opening_balance' => 300000,
                'cash_sales' => 0,
                'cash_expenses' => 0,
                'expected_balance' => 300000,
                'status' => 'open',
                'notes' => 'Afternoon shift session',
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

        // Inventory movements (restocks)
        InventoryMovement::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'reference_id' => 'restock-001'],
            [
                'product_id' => $products['ESP-001']->id,
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
        InventoryMovement::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'reference_id' => 'restock-002'],
            [
                'product_id' => $products['CAP-001']->id,
                'user_id' => $manager?->id ?? $owner?->id,
                'type' => 'purchase',
                'quantity' => 30,
                'unit_cost' => 9000,
                'total_cost' => 270000,
                'reason' => 'Restock milk & beans',
                'reference_type' => 'purchase_order',
                'notes' => 'Restocked cappuccino ingredients',
            ]
        );

        // Generate recent orders for today and the past 5 days (total 50 orders)
        // Use only enum-allowed methods from payments migration
        $paymentMethods = ['cash', 'qris', 'credit_card', 'debit_card', 'bank_transfer', 'e_wallet'];
        $todayCount = 10; // 10 orders today
        $pastDays = 4; // 4 days ago (total 5 days including today)

        $orderSeq = [];
        for ($d = 0; $d <= $pastDays; $d++) {
            $date = now()->subDays($d);
            // Distribute 50 orders across 5 days: 10 today, 10 per past day
            $ordersPerDay = $d === 0 ? $todayCount : 10;
            $dateKey = $date->format('Ymd');
            // Initialize sequence from existing orders on that date (if any)
            $orderSeq[$dateKey] = Order::withoutGlobalScopes()->whereDate('created_at', $date->toDateString())->count();
            for ($i = 0; $i < $ordersPerDay; $i++) {
                $orderTime = $date->copy()->startOfDay()->addMinutes(rand(8 * 60, 21 * 60));
                $customer = $i % 3 === 0 ? $member : null;
                // Generate unique order number per date
                $orderSeq[$dateKey]++;
                $orderNumber = 'ORD' . $dateKey . str_pad($orderSeq[$dateKey], 4, '0', STR_PAD_LEFT);

                $order = Order::withoutGlobalScopes()->create([
                    'store_id' => $store->id,
                    'user_id' => $cashier?->id ?? $owner?->id,
                    'member_id' => $customer?->id,
                    'order_number' => $orderNumber,
                    'status' => 'completed',
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'service_charge' => 0,
                    'total_amount' => 0,
                    'notes' => $d === 0 ? 'Order today' : 'Historical order',
                    'created_at' => $orderTime,
                    'updated_at' => $orderTime,
                    'completed_at' => $orderTime->copy()->addMinutes(5),
                ]);

                // Add 1-3 items per order
                $numItems = rand(1, 3);
                $lineTotal = 0;
                $skus = array_keys($products);
                shuffle($skus);
                $chosen = array_slice($skus, 0, $numItems);
                foreach ($chosen as $sku) {
                    $p = $products[$sku];
                    $qty = rand(1, 2);
                    $item = OrderItem::withoutStoreScope()->create([
                        'store_id' => $store->id,
                        'order_id' => $order->id,
                        'product_id' => $p->id,
                        'product_name' => $p->name,
                        'product_sku' => $p->sku,
                        'quantity' => $qty,
                        'unit_price' => $p->price,
                        'notes' => rand(0, 1) ? null : 'Less sugar',
                        'created_at' => $orderTime,
                        'updated_at' => $orderTime,
                    ]);
                    $lineTotal += (float) $item->total_price;

                    // Record simple COGS history using product cost_price
                    CogsHistory::withoutStoreScope()->create([
                        'store_id' => $store->id,
                        'product_id' => $p->id,
                        'order_id' => $order->id,
                        'quantity_sold' => $qty,
                        'unit_cost' => $p->cost_price,
                        'total_cogs' => $p->cost_price * $qty,
                        'calculation_method' => CogsHistory::METHOD_WEIGHTED_AVERAGE,
                        'created_at' => $orderTime,
                        'updated_at' => $orderTime,
                    ]);
                }

                // simple charges
                $tax = round($lineTotal * 0.1);
                $service = round($lineTotal * 0.05);
                $total = $lineTotal + $tax + $service;

                $order->update([
                    'subtotal' => $lineTotal,
                    'tax_amount' => $tax,
                    'service_charge' => $service,
                    'total_amount' => $total,
                ]);

                // Payment
                Payment::withoutStoreScope()->create([
                    'store_id' => $store->id,
                    'order_id' => $order->id,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'amount' => $total,
                    'status' => 'completed',
                    'processed_at' => $orderTime->copy()->addMinutes(6),
                    'notes' => 'Auto generated payment',
                    'created_at' => $orderTime->copy()->addMinutes(6),
                    'updated_at' => $orderTime->copy()->addMinutes(6),
                ]);
            }
        }

        // Staff performance sample
        StaffPerformance::withoutStoreScope()->updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $cashier?->id ?? $owner?->id, 'date' => now()->toDateString()],
            [
                'orders_processed' => $todayCount,
                'total_sales' => Payment::withoutStoreScope()->where('store_id', $store->id)->whereDate('created_at', now())->sum('amount'),
                'average_order_value' => Order::withoutGlobalScopes()->where('store_id', $store->id)->whereDate('created_at', now())->avg('total_amount') ?? 0,
                'additional_metrics' => [
                    'note' => 'Auto-generated performance data'
                ],
            ]
        );

        // Simple table occupancy snapshot (if tables exist)
        $table = Table::withoutStoreScope()->first();
        if ($table) {
            $table->update(['status' => 'available']);
        }

        $this->command?->info('Owner demo data seeded for store: ' . $store->name);
    }
}
