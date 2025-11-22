<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\User;

class CoffeeShopInventoryTransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic inventory transfers between stores for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Check if transfers already exist
        $existingTransfers = InventoryTransfer::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingTransfers > 0) {
            $this->command->info("⏭️  Tenant already has {$existingTransfers} transfer(s). Skipping...");
            return;
        }

        $this->command->info("🔄 Creating inventory transfers for tenant: {$tenant->name}");

        // Get stores (need at least 2 stores for transfers)
        $stores = Store::where('tenant_id', $tenantId)->get();
        if ($stores->count() < 2) {
            $this->command->warn('Need at least 2 stores for transfers. Skipping...');
            return;
        }

        $inventoryItems = InventoryItem::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('track_stock', true)
            ->limit(10)
            ->get();

        if ($inventoryItems->isEmpty()) {
            $this->command->warn('No inventory items found. Skipping transfers.');
            return;
        }

        $owner = User::where('email', 'owner@xpresspos.id')->first();

        // Create transfers between stores (Jakarta -> Bandung, Bandung -> Purwokerto, etc.)
        // Create 2-3 transfers
        $transferCount = min(3, $stores->count() - 1);
        
        for ($i = 0; $i < $transferCount; $i++) {
            $fromStore = $stores[$i];
            $toStore = $stores[($i + 1) % $stores->count()];
            
            // Random status (mostly received for demo)
            $statuses = ['received', 'received', 'shipped', 'approved'];
            $status = $statuses[array_rand($statuses)];
            
            // Random dates (past 20 days)
            $shippedAt = $status !== 'draft' ? now()->subDays(rand(1, 20)) : null;
            $receivedAt = $status === 'received' && $shippedAt ? $shippedAt->copy()->addDays(rand(1, 3)) : null;
            
            // Create transfer
            $transfer = InventoryTransfer::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'from_store_id' => $fromStore->id,
                'to_store_id' => $toStore->id,
                'transfer_number' => null, // Auto-generated
                'status' => $status,
                'shipped_at' => $shippedAt,
                'received_at' => $receivedAt,
                'notes' => "Transfer stok dari {$fromStore->name} ke {$toStore->name}",
            ]);

            // Get stock levels from source store
            $stockLevels = StockLevel::where('store_id', $fromStore->id)
                ->whereIn('inventory_item_id', $inventoryItems->pluck('id'))
                ->get()
                ->keyBy('inventory_item_id');

            // Create 2-4 transfer items
            $itemsToTransfer = $inventoryItems->random(rand(2, 4));

            foreach ($itemsToTransfer as $item) {
                $stockLevel = $stockLevels->get($item->id);
                $availableStock = $stockLevel ? $stockLevel->current_stock : 0;
                
                // Transfer amount (cannot exceed available stock)
                $quantityShipped = $availableStock > 0 ? rand(1, min(10, $availableStock)) : rand(5, 20);
                $quantityReceived = $status === 'received' ? $quantityShipped : ($status === 'shipped' ? 0 : 0);
                
                $unitCost = $item->default_cost ?? rand(5000, 50000);

                InventoryTransferItem::create([
                    'inventory_transfer_id' => $transfer->id,
                    'inventory_item_id' => $item->id,
                    'uom_id' => $item->uom_id,
                    'quantity_shipped' => $quantityShipped,
                    'quantity_received' => $quantityReceived,
                    'unit_cost' => $unitCost,
                ]);
            }

            $this->command->line("   ✓ Created transfer: {$transfer->transfer_number} from {$fromStore->name} to {$toStore->name} - Status: {$status}");
        }

        $this->command->info("✅ Successfully created inventory transfers for tenant: {$tenant->name}");
    }
}

