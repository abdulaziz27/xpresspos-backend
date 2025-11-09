<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Member;
use App\Models\MemberTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class OwnerPanelSeeder extends Seeder
{
    public function run(): void
    {
        // Normalize any legacy demo store name
        $legacy = Store::where('name', 'Demo Coffee Shop')->first();
        if ($legacy) {
            $legacy->update(['name' => 'Arasta Coffee - Central']);
        }

        $owner = User::firstOrCreate(
            ['email' => 'owner@xpresspos.com'],
            [
                'name' => 'Arasta Owner',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Rename any legacy English branch names to Indonesian to avoid duplicates
        $renameMap = [
            'Arasta Coffee - Central' => 'Arasta Coffee - Pusat',
            'Arasta Coffee - North' => 'Arasta Coffee - Utara',
            'Arasta Coffee - South' => 'Arasta Coffee - Selatan',
            'Arasta Coffee - East' => 'Arasta Coffee - Timur',
            'Arasta Coffee - West' => 'Arasta Coffee - Barat',
        ];
        foreach ($renameMap as $old => $new) {
            $st = Store::where('name', $old)->first();
            if ($st) { $st->update(['name' => $new]); }
        }

        $branchNames = [
            'Arasta Coffee - Pusat',
            'Arasta Coffee - Utara',
            'Arasta Coffee - Selatan',
            'Arasta Coffee - Timur',
            'Arasta Coffee - Barat',
        ];

        $categoriesSpec = [
            ['slug' => 'coffee', 'name' => 'Coffee', 'sort' => 1],
            ['slug' => 'non-coffee', 'name' => 'Non Coffee', 'sort' => 2],
            ['slug' => 'food', 'name' => 'Food', 'sort' => 3],
            ['slug' => 'bottle', 'name' => 'Bottle', 'sort' => 4],
        ];

        $productsSpec = [
            ['sku' => 'ESP-001', 'name' => 'Espresso', 'cat' => 'coffee', 'price' => 25000, 'cost' => 8000, 'stock' => 120, 'min' => 20],
            ['sku' => 'LAT-001', 'name' => 'Cafe Latte', 'cat' => 'coffee', 'price' => 35000, 'cost' => 12000, 'stock' => 60, 'min' => 25],
            ['sku' => 'CAP-001', 'name' => 'Cappuccino', 'cat' => 'coffee', 'price' => 34000, 'cost' => 11000, 'stock' => 18, 'min' => 20],
            ['sku' => 'AME-001', 'name' => 'Americano', 'cat' => 'coffee', 'price' => 28000, 'cost' => 7000, 'stock' => 45, 'min' => 15],
            ['sku' => 'MOC-001', 'name' => 'Mocha', 'cat' => 'coffee', 'price' => 38000, 'cost' => 14000, 'stock' => 22, 'min' => 20],
            ['sku' => 'TEA-001', 'name' => 'Lemon Tea', 'cat' => 'non-coffee', 'price' => 24000, 'cost' => 6000, 'stock' => 35, 'min' => 10],
            ['sku' => 'CHO-001', 'name' => 'Hot Chocolate', 'cat' => 'non-coffee', 'price' => 30000, 'cost' => 10000, 'stock' => 12, 'min' => 15],
            ['sku' => 'CRT-001', 'name' => 'Croissant', 'cat' => 'food', 'price' => 22000, 'cost' => 9000, 'stock' => 30, 'min' => 10],
            ['sku' => 'BAG-001', 'name' => 'Bagel', 'cat' => 'food', 'price' => 20000, 'cost' => 8000, 'stock' => 8, 'min' => 12],
            ['sku' => 'BCL-001', 'name' => 'Bottled Cold Latte', 'cat' => 'bottle', 'price' => 42000, 'cost' => 18000, 'stock' => 20, 'min' => 10],
        ];

        $paymentMethods = ['cash', 'qris', 'credit_card', 'debit_card', 'bank_transfer', 'e_wallet'];

        foreach ($branchNames as $idx => $branchName) {
            $store = Store::firstOrCreate(
                ['name' => $branchName],
                [
                    'email' => 'branch' . ($idx + 1) . '@arasta.coffee',
                    'phone' => '+62812' . rand(10000000, 99999999),
                    'address' => 'Arasta Branch ' . ($idx + 1),
                    'settings' => ['currency' => 'IDR', 'tax_rate' => 10, 'service_charge_rate' => 5],
                    'status' => 'active',
                ]
            );

            $ownerRole = \Spatie\Permission\Models\Role::where('name', 'owner')
                ->where('store_id', $store->id)
                ->first();
            if ($ownerRole) {
                setPermissionsTeamId($store->id);
                $owner->assignRole($ownerRole);
            }

            StoreUserAssignment::updateOrCreate(
                ['store_id' => $store->id, 'user_id' => $owner->id],
                ['assignment_role' => 'owner', 'is_primary' => $idx === 0]
            );

            // Ensure the owner's primary store_id points to Central branch
            if ($idx === 0 && $owner->store_id !== $store->id) {
                $owner->store_id = $store->id;
                $owner->save();
            }

            $categoryMap = [];
            foreach ($categoriesSpec as $cat) {
                $category = Category::withoutStoreScope()->updateOrCreate(
                    ['store_id' => $store->id, 'slug' => $cat['slug']],
                    [
                        'name' => $cat['name'],
                        'description' => $cat['name'],
                        'status' => true,
                        'sort_order' => $cat['sort'],
                    ]
                );
                $categoryMap[$cat['slug']] = $category;
            }

            $products = [];
            foreach ($productsSpec as $pd) {
                $products[$pd['sku']] = Product::withoutStoreScope()->updateOrCreate(
                    ['store_id' => $store->id, 'sku' => $pd['sku']],
                    [
                        'category_id' => $categoryMap[$pd['cat']]->id,
                        'name' => $pd['name'],
                        'description' => $pd['name'],
                        'price' => $pd['price'],
                        'cost_price' => $pd['cost'],
                        'track_inventory' => true,
                        'stock' => $pd['stock'],
                        'min_stock_level' => $pd['min'],
                        'status' => true,
                    ]
                );
            }

            $tier = MemberTier::withoutStoreScope()->where('store_id', $store->id)->ordered()->first();
            for ($m = 1; $m <= 40; $m++) {
                // Use a globally unique and deterministic member number based on store id hash
                $memberNumber = 'MBR' . strtoupper(substr(md5((string) $store->id), 0, 6)) . str_pad((string) $m, 4, '0', STR_PAD_LEFT);
                $legacyEmail = 'member' . $m . '@arasta.coffee';
                $memberEmail = 'member' . $m . '.b' . ($idx + 1) . '@arasta.coffee';

                // Prefer updating legacy record if it exists for this store to stay idempotent
                $member = Member::withoutStoreScope()->where('store_id', $store->id)->where('email', $legacyEmail)->first();
                if (!$member) {
                    $member = Member::withoutStoreScope()->firstOrCreate(
                        ['store_id' => $store->id, 'email' => $memberEmail],
                        [
                            'member_number' => $memberNumber,
                            'name' => 'Member ' . $m,
                            'phone' => '+62813' . rand(10000000, 99999999),
                            'loyalty_points' => rand(50, 1500),
                            'total_spent' => rand(500000, 15000000),
                            'visit_count' => rand(5, 100),
                            'tier_id' => $tier?->id,
                            'is_active' => true,
                            'last_visit_at' => now()->subDays(rand(0, 10)),
                        ]
                    );
                }
                if (!$member->wasRecentlyCreated) {
                    $member->update([
                        'name' => 'Member ' . $m,
                        'phone' => $member->phone ?: ('+62813' . rand(10000000, 99999999)),
                        'loyalty_points' => $member->loyalty_points ?: rand(50, 1500),
                        'total_spent' => $member->total_spent ?: rand(500000, 15000000),
                        'visit_count' => $member->visit_count ?: rand(5, 100),
                        'tier_id' => $member->tier_id ?: $tier?->id,
                        'is_active' => true,
                        'last_visit_at' => $member->last_visit_at ?: now()->subDays(rand(0, 10)),
                    ]);
                }
            }

            // Month-to-date daily targets per branch with overall ~23M/day
            $branchShares = [0.29, 0.24, 0.19, 0.15, 0.13];
            $dailyOverall = 23000000;
            $days = now()->day; // month-to-date days

            $seqByDate = [];
            for ($d = $days - 1; $d >= 0; $d--) {
                $date = now()->subDays($d);
                $dateKey = $date->format('Ymd');
                $seqByDate[$dateKey] = Order::withoutGlobalScopes()->where('store_id', $store->id)->whereDate('created_at', $date)->count();

                // Branch daily target with slight randomness
                $jitter = rand(90, 110) / 100; // +/-10%
                $dailyTarget = (int) round($dailyOverall * $branchShares[$idx] * $jitter);

                // Generate orders until reaching daily target
                $skuKeys = array_keys($products);
                $minute = 10;
                $dailySum = Payment::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->sum('amount');

                while ($dailySum < $dailyTarget) {
                    $time = $date->copy()->startOfDay()->addMinutes(9 * 60 + $minute);
                    $minute += rand(5, 12);

                    $seqByDate[$dateKey]++;
                    $orderNumber = 'ORD' . ($idx + 1) . $dateKey . str_pad((string) $seqByDate[$dateKey], 4, '0', STR_PAD_LEFT);
                    if (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists()) {
                        $minute += 2;
                        continue;
                    }

                    $order = Order::withoutGlobalScopes()->create([
                        'store_id' => $store->id,
                        'order_number' => $orderNumber,
                        'status' => 'completed',
                        'subtotal' => 0,
                        'tax_amount' => 0,
                        'discount_amount' => 0,
                        'service_charge' => 0,
                        'total_amount' => 0,
                        'created_at' => $time,
                        'updated_at' => $time,
                        'completed_at' => $time->copy()->addMinutes(5),
                    ]);

                    $numItems = rand(2, 4);
                    $lineTotal = 0;
                    shuffle($skuKeys);
                    foreach (array_slice($skuKeys, 0, $numItems) as $sku) {
                        $p = $products[$sku];
                        $qty = rand(1, 3);
                        $item = OrderItem::withoutStoreScope()->create([
                            'store_id' => $store->id,
                            'order_id' => $order->id,
                            'product_id' => $p->id,
                            'product_name' => $p->name,
                            'product_sku' => $p->sku,
                            'quantity' => $qty,
                            'unit_price' => $p->price,
                            'created_at' => $time,
                            'updated_at' => $time,
                        ]);
                        $lineTotal += (float) $item->total_price;
                    }

                    $tax = round($lineTotal * 0.1);
                    $service = round($lineTotal * 0.05);
                    $total = $lineTotal + $tax + $service;

                    $order->update([
                        'subtotal' => $lineTotal,
                        'tax_amount' => $tax,
                        'service_charge' => $service,
                        'total_amount' => $total,
                    ]);

                    Payment::withoutStoreScope()->create([
                        'store_id' => $store->id,
                        'order_id' => $order->id,
                        'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                        'amount' => $total,
                        'status' => 'completed',
                        'processed_at' => $time->copy()->addMinutes(6),
                        'created_at' => $time->copy()->addMinutes(6),
                        'updated_at' => $time->copy()->addMinutes(6),
                    ]);

                    $dailySum += (int) $total;
                    if ($minute > 21 * 60) {
                        break; // avoid infinite loop per day
                    }
                }
            }

            // Post-adjustment for Central: exact MTD revenue and transaction count target
            if ($idx === 0) {
                $monthStart = now()->startOfMonth();
                $monthEnd = now()->endOfMonth();
                $targetRevenue = 198134650;
                $currentRevenue = Payment::withoutStoreScope()
                    ->where('store_id', $store->id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount');

                $diff = (int) ($targetRevenue - $currentRevenue);
                if ($diff > 0) {
                    $today = now();
                    $dateKey = $today->format('Ymd');
                    $seqByDate[$dateKey] = ($seqByDate[$dateKey] ?? 0);
                    $skuKeys = array_keys($products);
                    $minute = 12;

                    while ($diff > 0) {
                        $time = $today->copy()->startOfDay()->addMinutes(10 * 60 + $minute);
                        $minute += rand(3, 8);
                        $seqByDate[$dateKey]++;
                        $orderNumber = 'ORD' . (1) . $dateKey . str_pad((string) $seqByDate[$dateKey], 4, '0', STR_PAD_LEFT);
                        if (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists()) {
                            $minute += 2;
                            continue;
                        }
                        $order = Order::withoutGlobalScopes()->create([
                            'store_id' => $store->id,
                            'order_number' => $orderNumber,
                            'status' => 'completed',
                            'subtotal' => 0,
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                            'service_charge' => 0,
                            'total_amount' => 0,
                            'created_at' => $time,
                            'updated_at' => $time,
                            'completed_at' => $time->copy()->addMinutes(5),
                        ]);

                        shuffle($skuKeys);
                        $p = $products[$skuKeys[0]];
                        $qty = 1;
                        $item = OrderItem::withoutStoreScope()->create([
                            'store_id' => $store->id,
                            'order_id' => $order->id,
                            'product_id' => $p->id,
                            'product_name' => $p->name,
                            'product_sku' => $p->sku,
                            'quantity' => $qty,
                            'unit_price' => $p->price,
                            'created_at' => $time,
                            'updated_at' => $time,
                        ]);
                        $lineTotal = (float) $item->total_price;
                        $tax = round($lineTotal * 0.1);
                        $service = round($lineTotal * 0.05);
                        $total = $lineTotal + $tax + $service;
                        $order->update([
                            'subtotal' => $lineTotal,
                            'tax_amount' => $tax,
                            'service_charge' => $service,
                            'total_amount' => $total,
                        ]);
                        Payment::withoutStoreScope()->create([
                            'store_id' => $store->id,
                            'order_id' => $order->id,
                            'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                            'amount' => $total,
                            'status' => 'completed',
                            'processed_at' => $time->copy()->addMinutes(6),
                            'created_at' => $time->copy()->addMinutes(6),
                            'updated_at' => $time->copy()->addMinutes(6),
                        ]);
                        $diff -= (int) $total;
                        if ($minute > 21 * 60) break;
                    }
                }

                // Ensure ~1429 transactions (orders) MTD
                $currentOrders = Order::withoutGlobalScopes()
                    ->where('store_id', $store->id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();
                $targetOrders = 1429;
                $addOrders = max(0, $targetOrders - $currentOrders);
                if ($addOrders > 0) {
                    $today = now();
                    $dateKey = $today->format('Ymd');
                    $seqByDate[$dateKey] = ($seqByDate[$dateKey] ?? 0);
                    $skuKeys = array_keys($products);
                    $minute = 13;
                    for ($i = 0; $i < $addOrders; $i++) {
                        $time = $today->copy()->startOfDay()->addMinutes(11 * 60 + $minute);
                        $minute += 2;
                        $seqByDate[$dateKey]++;
                        $orderNumber = 'ORD' . (1) . $dateKey . str_pad((string) $seqByDate[$dateKey], 4, '0', STR_PAD_LEFT);
                        if (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists()) {
                            $minute += 1;
                            continue;
                        }
                        $order = Order::withoutGlobalScopes()->create([
                            'store_id' => $store->id,
                            'order_number' => $orderNumber,
                            'status' => 'completed',
                            'subtotal' => 0,
                            'tax_amount' => 0,
                            'discount_amount' => 0,
                            'service_charge' => 0,
                            'total_amount' => 0,
                            'created_at' => $time,
                            'updated_at' => $time,
                            'completed_at' => $time->copy()->addMinutes(5),
                        ]);
                        shuffle($skuKeys);
                        $p = $products[$skuKeys[0]];
                        $qty = 1;
                        $item = OrderItem::withoutStoreScope()->create([
                            'store_id' => $store->id,
                            'order_id' => $order->id,
                            'product_id' => $p->id,
                            'product_name' => $p->name,
                            'product_sku' => $p->sku,
                            'quantity' => $qty,
                            'unit_price' => $p->price,
                            'created_at' => $time,
                            'updated_at' => $time,
                        ]);
                        $lineTotal = (float) $item->total_price;
                        $tax = round($lineTotal * 0.1);
                        $service = round($lineTotal * 0.05);
                        $total = $lineTotal + $tax + $service;
                        $order->update([
                            'subtotal' => $lineTotal,
                            'tax_amount' => $tax,
                            'service_charge' => $service,
                            'total_amount' => $total,
                        ]);
                        Payment::withoutStoreScope()->create([
                            'store_id' => $store->id,
                            'order_id' => $order->id,
                            'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                            'amount' => $total,
                            'status' => 'completed',
                            'processed_at' => $time->copy()->addMinutes(6),
                            'created_at' => $time->copy()->addMinutes(6),
                            'updated_at' => $time->copy()->addMinutes(6),
                        ]);
                    }
                }
            }
        }
    }
}


