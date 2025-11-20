<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchemaUpgradeTestSeeder extends Seeder
{
    /**
     * Run the database seeds for schema upgrade testing.
     * This seeder creates minimal test data to verify migration integrity.
     */
    public function run(): void
    {
        $this->command->info('🌱 Creating sample data for schema upgrade testing...');
        
        // Get or create first tenant
        $tenant = DB::table('tenants')->first();
        if (!$tenant) {
            $this->command->warn('  No tenants found. Creating test tenant...');
            $tenantId = (string) Str::uuid();
            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => 'Test Tenant',
                'email' => 'test@tenant.com',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        }
        
        // Get or create first store
        $store = DB::table('stores')->where('tenant_id', $tenant->id)->first();
        if (!$store) {
            $this->command->warn('  No stores found for tenant. Creating test store...');
            $storeId = (string) Str::uuid();
            DB::table('stores')->insert([
                'id' => $storeId,
                'tenant_id' => $tenant->id,
                'name' => 'Test Store',
                'email' => 'test@store.com',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $store = DB::table('stores')->where('id', $storeId)->first();
        }
        
        $this->command->info("  Using Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->command->info("  Using Store: {$store->name} (ID: {$store->id})");
        
        // Create sample inventory items
        $this->createInventoryItems($store->id);
        
        // Create sample suppliers
        $this->createSuppliers($tenant->id, $store->id);
        
        // Create sample promotions
        $this->createPromotions($tenant->id, $store->id);
        
        $this->command->info('✅ Sample data created successfully!');
    }
    
    private function createInventoryItems(string $storeId): void
    {
        $this->command->info('  Creating inventory items...');
        
        $uom = DB::table('uoms')->where('code', 'pcs')->first();
        if (!$uom) {
            $this->command->warn('  UOM not found, skipping inventory items');
            return;
        }
        
        $items = [
            ['name' => 'Coffee Beans', 'sku' => 'TEST-CB-001'],
            ['name' => 'Sugar', 'sku' => 'TEST-SG-001'],
            ['name' => 'Milk', 'sku' => 'TEST-MK-001'],
        ];
        
        foreach ($items as $item) {
            // Check if already exists
            $existing = DB::table('inventory_items')
                ->where('store_id', $storeId)
                ->where('sku', $item['sku'])
                ->first();
            
            if ($existing) {
                continue;
            }
            
            $itemId = (string) Str::uuid();
            
            DB::table('inventory_items')->insert([
                'id' => $itemId,
                'store_id' => $storeId,
                'name' => $item['name'],
                'sku' => $item['sku'],
                'uom_id' => $uom->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Create stock level
            DB::table('inventory_stock_levels')->insert([
                'id' => (string) Str::uuid(),
                'store_id' => $storeId,
                'inventory_item_id' => $itemId,
                'current_stock' => 100,
                'reserved_stock' => 0,
                'available_stock' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('    ✓ Created inventory items');
    }
    
    private function createSuppliers(string $tenantId, string $storeId): void
    {
        $this->command->info('  Creating suppliers...');
        
        $suppliers = [
            ['name' => 'Test Supplier 1', 'email' => 'supplier1@test.com'],
            ['name' => 'Test Supplier 2', 'email' => 'supplier2@test.com'],
        ];
        
        foreach ($suppliers as $supplier) {
            // Check if already exists
            $existing = DB::table('suppliers')
                ->where('tenant_id', $tenantId)
                ->where('email', $supplier['email'])
                ->first();
            
            if ($existing) {
                continue;
            }
            
            DB::table('suppliers')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'name' => $supplier['name'],
                'email' => $supplier['email'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('    ✓ Created suppliers');
    }
    
    private function createPromotions(string $tenantId, string $storeId): void
    {
        $this->command->info('  Creating promotions...');
        
        // Check if promotion already exists
        $existing = DB::table('promotions')
            ->where('tenant_id', $tenantId)
            ->where('name', 'Test Promotion')
            ->first();
        
        if ($existing) {
            $this->command->info('    ✓ Promotions already exist');
            return;
        }
        
        $promotionId = (string) Str::uuid();
        
        DB::table('promotions')->insert([
            'id' => $promotionId,
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'name' => 'Test Promotion',
            'description' => 'Test promotion for schema upgrade',
            'type' => 'AUTOMATIC',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Add condition
        DB::table('promotion_conditions')->insert([
            'promotion_id' => $promotionId,
            'condition_type' => 'MIN_SPEND',
            'condition_value' => json_encode(['amount' => 50000]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Add reward
        DB::table('promotion_rewards')->insert([
            'promotion_id' => $promotionId,
            'reward_type' => 'PCT_OFF',
            'reward_value' => json_encode(['percentage' => 10]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->command->info('    ✓ Created promotions');
    }
}
