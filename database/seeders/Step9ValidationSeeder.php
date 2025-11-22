<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class Step9ValidationSeeder extends Seeder
{
    /**
     * Run the database seeds for Step 9 Validation.
     * Creates test scenario: 1 tenant, 2 stores, 1 owner user, master data
     */
    public function run(): void
    {
        // 1. Create 1 Tenant (or get existing)
        $tenant = Tenant::firstOrCreate(
            ['email' => 'test-tenant-step9@example.com'],
            [
                'name' => 'Test Tenant',
                'phone' => '+6281234567890',
                'status' => 'active',
            ]
        );

        $this->command->info("✅ Created/found tenant: {$tenant->name} (ID: {$tenant->id})");

        // 2. Create 2 Stores for the Tenant
        $storeA = Store::firstOrCreate(
            ['email' => 'store-a-step9@example.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Store A',
                'code' => 'STORE-A-STEP9',
                'phone' => '+6281234567891',
                'address' => 'Address Store A',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
            ]
        );

        $storeB = Store::firstOrCreate(
            ['email' => 'store-b-step9@example.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Store B',
                'code' => 'STORE-B-STEP9',
                'phone' => '+6281234567892',
                'address' => 'Address Store B',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
            ]
        );

        $this->command->info("✅ Created store: {$storeA->name} (ID: {$storeA->id})");
        $this->command->info("✅ Created store: {$storeB->name} (ID: {$storeB->id})");

        // 3. Create 1 User Owner (or get existing)
        $owner = User::firstOrCreate(
            ['email' => 'test-owner-step9@example.com'],
            [
                'name' => 'Test Owner',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign to tenant via user_tenant_access (if not exists)
        $existingAccess = DB::table('user_tenant_access')
            ->where('user_id', $owner->id)
            ->where('tenant_id', $tenant->id)
            ->first();
        
        if (!$existingAccess) {
            DB::table('user_tenant_access')->insert([
                'id' => Str::uuid()->toString(),
                'user_id' => $owner->id,
                'tenant_id' => $tenant->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign to both stores via store_user_assignments (if not exists)
        StoreUserAssignment::firstOrCreate(
            [
                'store_id' => $storeA->id,
                'user_id' => $owner->id,
            ],
            [
                'assignment_role' => 'owner',
                'is_primary' => true, // Set primary store to Store A
            ]
        );

        StoreUserAssignment::firstOrCreate(
            [
                'store_id' => $storeB->id,
                'user_id' => $owner->id,
            ],
            [
                'assignment_role' => 'owner',
                'is_primary' => false,
            ]
        );

        // Assign owner role (tenant-scoped)
        $ownerRole = Role::where('name', 'owner')
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($ownerRole) {
            setPermissionsTeamId($tenant->id);
            $owner->assignRole($ownerRole);
        }

        $this->command->info("✅ Created owner user: {$owner->email}");
        $this->command->info("   - Assigned to tenant: {$tenant->name}");
        $this->command->info("   - Assigned to stores: {$storeA->name} (primary), {$storeB->name}");

        // 4. Create Master Data (Tenant-Scoped) - Only Once
        $category = Category::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('slug', 'test-category')
            ->first();
        
        if (!$category) {
            $category = Category::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Test Category',
                'slug' => 'test-category',
                'description' => 'Test category for validation',
                'status' => true,
                'sort_order' => 0,
            ]);
        }

        $product1 = Product::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('sku', 'PROD-001')
            ->first();
        
        if (!$product1) {
            $product1 = Product::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $category->id,
                'name' => 'Product 1',
                'sku' => 'PROD-001',
                'description' => 'Test product 1',
                'price' => 10000.00,
                'cost_price' => 5000.00,
                'track_inventory' => true,
                'status' => true,
                'is_favorite' => false,
                'sort_order' => 0,
            ]);
        }

        $product2 = Product::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('sku', 'PROD-002')
            ->first();
        
        if (!$product2) {
            $product2 = Product::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $category->id,
                'name' => 'Product 2',
                'sku' => 'PROD-002',
                'description' => 'Test product 2',
                'price' => 20000.00,
                'cost_price' => 10000.00,
                'track_inventory' => true,
                'status' => true,
                'is_favorite' => false,
                'sort_order' => 1,
            ]);
        }

        // Get UOM for inventory item (assuming there's a default UOM)
        $uom = \App\Models\Uom::first();
        if (!$uom) {
            $uom = \App\Models\Uom::create([
                'id' => Str::uuid()->toString(),
                'code' => 'PCS',
                'name' => 'Piece',
                'description' => 'Unit of measurement for counting items',
            ]);
        }

        $inventoryItem = InventoryItem::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('sku', 'INV-001')
            ->first();
        
        if (!$inventoryItem) {
            $inventoryItem = InventoryItem::withoutTenantScope()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Test Inventory Item',
                'sku' => 'INV-001',
                'category' => 'Raw Material',
                'uom_id' => $uom->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 10,
                'default_cost' => 5000.00,
                'status' => 'active',
            ]);
        }

        $this->command->info("✅ Created master data:");
        $this->command->info("   - Category: {$category->name}");
        $this->command->info("   - Product 1: {$product1->name} (SKU: {$product1->sku})");
        $this->command->info("   - Product 2: {$product2->name} (SKU: {$product2->sku})");
        $this->command->info("   - Inventory Item: {$inventoryItem->name} (SKU: {$inventoryItem->sku})");

        $this->command->info("\n📋 Test Data Summary:");
        $this->command->info("   Tenant: {$tenant->name} ({$tenant->id})");
        $this->command->info("   Stores: {$storeA->name}, {$storeB->name}");
        $this->command->info("   Owner: {$owner->email} / password123");
        $this->command->info("   Primary Store: {$storeA->name}");
        $this->command->info("\n✅ Step 9 validation test data created successfully!");
    }
}

