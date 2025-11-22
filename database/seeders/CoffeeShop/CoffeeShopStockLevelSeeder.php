<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\StockLevel;
use App\Models\InventoryItem;
use App\Models\Store;

class CoffeeShopStockLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic initial stock levels for inventory items.
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

        // Get all inventory items that track stock
        $inventoryItems = InventoryItem::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('track_stock', true)
            ->get();

        // Create stock levels for each store
        // Track which items will be low stock to ensure diversity
        $lowStockCount = 0;
        $totalItems = $inventoryItems->count() * $stores->count();
        $targetLowStockItems = max(3, (int) ($totalItems * 0.15)); // 15% of items will be low stock (min 3)
        
        foreach ($stores as $storeIndex => $store) {
            foreach ($inventoryItems as $itemIndex => $inventoryItem) {
                // Generate realistic initial stock based on item category and min_stock_level
                $minStock = $inventoryItem->min_stock_level ?? 0;
                
                if ($minStock <= 0) {
                    // If no min stock level, use default stock
                    $initialStock = rand(10, 50);
                } else {
                    // Determine if this item should be low stock (to trigger dashboard alert)
                    // Ensure at least targetLowStockItems will be low stock across all stores
                    $itemKey = ($storeIndex * $inventoryItems->count()) + $itemIndex;
                    $shouldBeLowStock = ($lowStockCount < $targetLowStockItems) && 
                                        (($itemKey % 7 === 0) || rand(1, 100) <= 15); // 15% chance or every 7th item
                    
                    if ($shouldBeLowStock && $lowStockCount < $targetLowStockItems) {
                        // Low stock: 20% to 100% of min_stock_level (will trigger alert: current_stock <= min_stock_level)
                        // Some items exactly at minimum (100%), some below (20-99%)
                        $lowStockPercentage = rand(20, 100) / 100; // 20% to 100%
                        $initialStock = max(0, $minStock * $lowStockPercentage);
                        $lowStockCount++;
                        $this->command->line("   ⚠️  Low stock item: {$inventoryItem->name} at {$store->name} - Stock: {$initialStock} (Min: {$minStock})");
                    } else {
                        // Normal stock: 2-4x min_stock_level for realistic data
                        $initialStock = $minStock * (2 + (rand(0, 200) / 100)); // 2x to 4x
                    }
                }
                
                $averageCost = $inventoryItem->default_cost ?? 0;
                $totalValue = $initialStock * $averageCost;

                StockLevel::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'store_id' => $store->id,
                        'inventory_item_id' => $inventoryItem->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'store_id' => $store->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'current_stock' => $initialStock,
                        'reserved_stock' => 0,
                        'available_stock' => $initialStock,
                        'min_stock_level' => $minStock,
                        'average_cost' => $averageCost,
                        'total_value' => $totalValue,
                        'last_movement_at' => now()->subDays(rand(0, 7)), // Random date within last week
                    ]
                );
            }
        }
        
        if ($lowStockCount > 0) {
            $this->command->info("⚠️  Created {$lowStockCount} low stock items (will appear in LowStockWidget dashboard)");
        }
        
        $this->command->info("✅ Coffee shop stock levels created successfully for {$stores->count()} stores!");
    }
}

