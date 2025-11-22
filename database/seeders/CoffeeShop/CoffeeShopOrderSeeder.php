<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Member;
use App\Models\User;
use App\Models\Store;

class CoffeeShopOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic orders for coffee shop with various dates and products.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        $stores = Store::where('tenant_id', $tenantId)->get();

        if ($stores->isEmpty()) {
            $this->command->error('No stores found. Make sure StoreSeeder runs first.');
            return;
        }

        // Get users per store - cashier yang assigned ke store tersebut
        $storeUsers = [];
        foreach ($stores as $store) {
            $storeUsers[$store->id] = User::whereHas('storeAssignments', function ($query) use ($store) {
                $query->where('store_id', $store->id);
            })->get();
            
            // Fallback to owner if no users assigned
            if ($storeUsers[$store->id]->isEmpty()) {
                $owner = User::where('email', 'owner@xpresspos.id')->first();
                if ($owner) {
                    $storeUsers[$store->id] = collect([$owner]);
                }
            }
        }

        // Get products
        $products = Product::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('sku');

        // Get members
        $members = Member::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get();

        // Generate orders for the last 30 days, distributed across all stores
        $orderCount = 0;
        $daysBack = 30;

        for ($day = 0; $day <= $daysBack; $day++) {
            $date = Carbon::now()->subDays($day);
            
            // Generate different number of orders per day per store
            // More orders on weekends, fewer on weekdays
            $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 6 = Saturday
            $isWeekend = $dayOfWeek == 0 || $dayOfWeek == 6;
            $baseOrdersPerDay = $isWeekend ? rand(8, 15) : rand(5, 12);
            
            // Distribute orders across stores (divide by store count)
            $ordersPerStore = max(1, intval($baseOrdersPerDay / $stores->count()));

            // Track order sequence per day for unique order numbers per store
            $dayOrderSequences = [];
            foreach ($stores as $store) {
                $dayOrderSequences[$store->id] = 1;
            }

            // Generate orders for each store
            foreach ($stores as $store) {
                for ($orderNum = 0; $orderNum < $ordersPerStore; $orderNum++) {
                    // Random time throughout the day
                    $hour = rand(7, 21); // 7 AM to 9 PM
                    $minute = rand(0, 59);
                    $orderDate = $date->copy()->setTime($hour, $minute);

                    // Use cashier/owner yang assigned ke store ini
                    $user = $storeUsers[$store->id]->random();
                    $isMember = rand(0, 100) < 40; // 40% chance member order
                    $member = $isMember && $members->isNotEmpty() ? $members->random() : null;

                    // Random operation mode
                    $operationModes = ['dine_in', 'takeaway', 'delivery'];
                    $operationMode = $operationModes[array_rand($operationModes)];

                    // Random status (mostly completed for historical data)
                    $statuses = ['completed', 'completed', 'completed', 'completed', 'cancelled'];
                    $status = $statuses[array_rand($statuses)];

                    // Select 1-4 products randomly
                    $selectedProducts = $products->random(min(rand(1, 4), $products->count()));

                    $subtotal = 0;
                    $items = [];

                    foreach ($selectedProducts as $product) {
                        $quantity = rand(1, 3);
                        $unitPrice = $product->price;

                        // Add random modifiers (30% chance)
                        $productOptions = [];
                        if (rand(0, 100) < 30 && $product->modifierGroups()->count() > 0) {
                            $modifierGroups = $product->modifierGroups()->get();
                            foreach ($modifierGroups as $modifierGroup) {
                                if (rand(0, 100) < 50) { // 50% chance to add modifier
                                    $modifierItems = $modifierGroup->items()->active()->get();
                                    if ($modifierItems->isNotEmpty()) {
                                        $selectedItem = $modifierItems->random();
                                        $productOptions[] = [
                                            'modifier_group_id' => $modifierGroup->id,
                                            'modifier_group_name' => $modifierGroup->name,
                                            'modifier_item_id' => $selectedItem->id,
                                            'modifier_item_name' => $selectedItem->name,
                                            'price_adjustment' => $selectedItem->price_delta,
                                        ];
                                        $unitPrice += $selectedItem->price_delta;
                                    }
                                }
                            }
                        }

                        $totalPrice = $unitPrice * $quantity;
                        $subtotal += $totalPrice;

                        $items[] = [
                            'product' => $product,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $totalPrice,
                            'product_options' => $productOptions,
                        ];
                    }

                    // Calculate totals
                    $taxRate = 0.10; // 10%
                    $serviceChargeRate = 0.05; // 5%
                    $taxAmount = $subtotal * $taxRate;
                    $serviceCharge = $subtotal * $serviceChargeRate;
                    $discountAmount = 0;

                    // Apply tier discount if member
                    if ($member && $member->tier) {
                        $discountPercentage = $member->tier->discount_percentage ?? 0;
                        if ($discountPercentage > 0) {
                            $discountAmount = $subtotal * ($discountPercentage / 100);
                        }
                    }

                    $totalAmount = $subtotal + $taxAmount + $serviceCharge - $discountAmount;

                    // Generate unique order number per store
                    $orderNumber = 'ORD' . $orderDate->format('Ymd') . '-' . substr($store->code, -3) . str_pad($dayOrderSequences[$store->id]++, 4, '0', STR_PAD_LEFT);
                
                    // Check if order with this number already exists (idempotent)
                    $existingOrder = Order::query()->withoutGlobalScopes()
                        ->where('order_number', $orderNumber)
                        ->first();
                    
                    if ($existingOrder) {
                        continue; // Skip if order already exists
                    }
                
                    // Create order
                    $order = Order::query()->withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'store_id' => $store->id,
                    'user_id' => $user->id,
                    'member_id' => $member?->id,
                    'customer_name' => $member ? $member->name : 'Guest Customer',
                    'customer_type' => $member ? 'member' : 'walk_in',
                    'operation_mode' => $operationMode,
                    'payment_mode' => 'direct',
                    'status' => $status,
                    'order_number' => $orderNumber,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'service_charge' => $serviceCharge,
                    'total_amount' => $totalAmount,
                    'notes' => rand(0, 100) < 10 ? 'Request extra hot' : null, // 10% chance
                    'completed_at' => $status === 'completed' ? $orderDate->copy()->addMinutes(rand(5, 30)) : null,
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                    // Create order items
                    foreach ($items as $item) {
                        OrderItem::create([
                            'store_id' => $store->id,
                            'order_id' => $order->id,
                            'product_id' => $item['product']->id,
                            'product_name' => $item['product']->name,
                            'product_sku' => $item['product']->sku,
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $item['total_price'],
                            'product_options' => $item['product_options'],
                            'created_at' => $orderDate,
                            'updated_at' => $orderDate,
                        ]);
                    }

                    $orderCount++;
                }
            }
        }

        $this->command->info("✅ Created {$orderCount} coffee shop orders successfully!");
    }
}

