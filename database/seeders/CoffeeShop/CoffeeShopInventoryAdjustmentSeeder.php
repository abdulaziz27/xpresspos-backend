<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\User;

class CoffeeShopInventoryAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic inventory adjustments for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Check if adjustments already exist
        $existingAdjustments = InventoryAdjustment::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingAdjustments > 0) {
            $this->command->info("⏭️  Tenant already has {$existingAdjustments} adjustment(s). Skipping...");
            return;
        }

        $this->command->info("📊 Creating inventory adjustments for tenant: {$tenant->name}");

        // Get stores, inventory items, and users
        $stores = Store::where('tenant_id', $tenantId)->get();
        if ($stores->isEmpty()) {
            $this->command->error('No stores found for tenant.');
            return;
        }

        $inventoryItems = InventoryItem::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('track_stock', true)
            ->limit(15)
            ->get();

        if ($inventoryItems->isEmpty()) {
            $this->command->warn('No inventory items found. Skipping adjustments.');
            return;
        }

        $owner = User::where('email', 'owner@xpresspos.id')->first();
        $cashiers = User::whereHas('roles', function ($q) {
            $q->where('name', 'cashier');
        })->get();

        $reasons = [
            InventoryAdjustment::REASON_COUNT_DIFF => 'Selisih stok saat stock opname',
            InventoryAdjustment::REASON_EXPIRED => 'Barang expired',
            InventoryAdjustment::REASON_DAMAGE => 'Barang rusak',
        ];

        // Create adjustments for each store
        foreach ($stores as $store) {
            // Create 1-2 adjustments per store
            $adjustmentCount = rand(1, 2);
            
            for ($i = 0; $i < $adjustmentCount; $i++) {
                $reason = array_rand($reasons);
                $adjustedAt = now()->subDays(rand(1, 15));
                
                // Create adjustment
                $adjustment = InventoryAdjustment::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'store_id' => $store->id,
                    'user_id' => $cashiers->isNotEmpty() ? $cashiers->random()->id : $owner->id,
                    'adjustment_number' => null, // Auto-generated
                    'status' => InventoryAdjustment::STATUS_APPROVED,
                    'reason' => $reason,
                    'adjusted_at' => $adjustedAt,
                    'notes' => $reasons[$reason],
                ]);

                // Get stock levels for this store
                $stockLevels = StockLevel::where('store_id', $store->id)
                    ->whereIn('inventory_item_id', $inventoryItems->pluck('id'))
                    ->get()
                    ->keyBy('inventory_item_id');

                // Create 2-4 adjustment items
                $itemsToAdjust = $inventoryItems->random(rand(2, 4));

                foreach ($itemsToAdjust as $item) {
                    $stockLevel = $stockLevels->get($item->id);
                    $systemQty = $stockLevel ? $stockLevel->current_stock : 0;
                    
                    // Create realistic difference based on reason
                    if ($reason === InventoryAdjustment::REASON_EXPIRED || $reason === InventoryAdjustment::REASON_DAMAGE) {
                        // Negative adjustment (decrease)
                        $countedQty = max(0, $systemQty - rand(1, 5));
                    } else {
                        // Count difference (can be positive or negative)
                        $countedQty = max(0, $systemQty + rand(-3, 3));
                    }
                    
                    $differenceQty = $countedQty - $systemQty;
                    
                    if ($differenceQty == 0) {
                        continue; // Skip if no difference
                    }

                    $unitCost = $item->default_cost ?? rand(5000, 50000);
                    $totalCost = abs($differenceQty) * $unitCost;

                    InventoryAdjustmentItem::create([
                        'inventory_adjustment_id' => $adjustment->id,
                        'inventory_item_id' => $item->id,
                        'system_qty' => $systemQty,
                        'counted_qty' => $countedQty,
                        'difference_qty' => $differenceQty,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                    ]);
                }

                // Generate inventory movements (this will update stock levels)
                $adjustment->generateInventoryMovements();

                $this->command->line("   ✓ Created adjustment: {$adjustment->adjustment_number} for {$store->name} - Reason: {$reasons[$reason]}");
            }
        }

        $this->command->info("✅ Successfully created inventory adjustments for tenant: {$tenant->name}");
    }
}

