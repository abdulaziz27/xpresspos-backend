<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\InventoryItem;
use App\Models\User;

class CoffeeShopPurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic purchase orders for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Check if purchase orders already exist
        $existingPOs = PurchaseOrder::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingPOs > 0) {
            $this->command->info("⏭️  Tenant already has {$existingPOs} purchase order(s). Skipping...");
            return;
        }

        $this->command->info("📦 Creating purchase orders for tenant: {$tenant->name}");

        // Get stores, suppliers, inventory items, and users
        $stores = Store::where('tenant_id', $tenantId)->get();
        if ($stores->isEmpty()) {
            $this->command->error('No stores found for tenant.');
            return;
        }

        $suppliers = Supplier::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();

        if ($suppliers->isEmpty()) {
            $this->command->warn('No suppliers found. Skipping purchase orders.');
            return;
        }

        $inventoryItems = InventoryItem::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->limit(10)
            ->get();

        if ($inventoryItems->isEmpty()) {
            $this->command->warn('No inventory items found. Skipping purchase orders.');
            return;
        }

        $owner = User::where('email', 'owner@xpresspos.id')->first();

        // Create purchase orders for each store
        foreach ($stores as $store) {
            // Create 2-3 purchase orders per store
            $poCount = rand(2, 3);
            
            for ($i = 0; $i < $poCount; $i++) {
                $supplier = $suppliers->random();
                
                // Random status (mostly received for demo)
                $statuses = ['received', 'received', 'received', 'approved', 'draft'];
                $status = $statuses[array_rand($statuses)];
                
                // Random dates (past 30 days)
                $orderedAt = now()->subDays(rand(1, 30));
                $receivedAt = $status === 'received' ? $orderedAt->copy()->addDays(rand(1, 3)) : null;
                
                // Create purchase order
                $po = PurchaseOrder::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'store_id' => $store->id,
                    'supplier_id' => $supplier->id,
                    'po_number' => null, // Auto-generated
                    'status' => $status,
                    'ordered_at' => $orderedAt,
                    'received_at' => $receivedAt,
                    'total_amount' => 0, // Will be calculated from items
                    'notes' => "Purchase order untuk restock bahan baku {$store->name}",
                ]);

                // Create 2-4 items per PO
                $itemsToOrder = $inventoryItems->random(rand(2, 4));
                $totalAmount = 0;

                foreach ($itemsToOrder as $item) {
                    $quantity = rand(10, 50);
                    $unitCost = $item->default_cost ?? rand(5000, 50000);
                    $totalCost = $quantity * $unitCost;
                    
                    $quantityReceived = $status === 'received' ? $quantity : ($status === 'approved' ? rand(0, $quantity) : 0);

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'inventory_item_id' => $item->id,
                        'uom_id' => $item->uom_id,
                        'quantity_ordered' => $quantity,
                        'quantity_received' => $quantityReceived,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                    ]);

                    $totalAmount += $totalCost;
                }

                // Update total amount
                $po->update(['total_amount' => $totalAmount]);

                $this->command->line("   ✓ Created PO: {$po->po_number} for {$store->name} - Status: {$status}");
            }
        }

        $this->command->info("✅ Successfully created purchase orders for tenant: {$tenant->name}");
    }
}

