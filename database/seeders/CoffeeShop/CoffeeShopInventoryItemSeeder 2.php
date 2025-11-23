<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryItem;
use App\Models\Store;

class CoffeeShopInventoryItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic inventory items for coffee shop.
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        // Get UOMs
        $uomKg = DB::table('uoms')->where('code', 'kg')->first();
        $uomG = DB::table('uoms')->where('code', 'g')->first();
        $uomL = DB::table('uoms')->where('code', 'l')->first();
        $uomMl = DB::table('uoms')->where('code', 'ml')->first();
        $uomPcs = DB::table('uoms')->where('code', 'pcs')->first();

        if (!$uomKg || !$uomG || !$uomL || !$uomMl || !$uomPcs) {
            $this->command->error('Required UOMs not found. Make sure UomSeeder runs first.');
            return;
        }

        $inventoryItems = [
            // Coffee beans & grounds
            [
                'tenant_id' => $tenantId,
                'name' => 'Arabica Coffee Beans',
                'sku' => 'INV-COFFEE-ARABICA',
                'category' => 'Coffee',
                'uom_id' => $uomKg->id,
                'track_lot' => true,
                'track_stock' => true,
                'min_stock_level' => 5.0, // 5 kg
                'default_cost' => 85000, // per kg
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Robusta Coffee Beans',
                'sku' => 'INV-COFFEE-ROBUSTA',
                'category' => 'Coffee',
                'uom_id' => $uomKg->id,
                'track_lot' => true,
                'track_stock' => true,
                'min_stock_level' => 3.0,
                'default_cost' => 65000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Espresso Grounds',
                'sku' => 'INV-COFFEE-ESPRESSO-GROUND',
                'category' => 'Coffee',
                'uom_id' => $uomKg->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 2.0,
                'default_cost' => 95000,
                'status' => 'active',
            ],
            
            // Dairy products
            [
                'tenant_id' => $tenantId,
                'name' => 'Fresh Milk',
                'sku' => 'INV-DAIRY-MILK-FRESH',
                'category' => 'Dairy',
                'uom_id' => $uomL->id,
                'track_lot' => true,
                'track_stock' => true,
                'min_stock_level' => 10.0, // 10 liters
                'default_cost' => 18000, // per liter
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Oat Milk',
                'sku' => 'INV-DAIRY-MILK-OAT',
                'category' => 'Dairy',
                'uom_id' => $uomL->id,
                'track_lot' => true,
                'track_stock' => true,
                'min_stock_level' => 5.0,
                'default_cost' => 32000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Soy Milk',
                'sku' => 'INV-DAIRY-MILK-SOY',
                'category' => 'Dairy',
                'uom_id' => $uomL->id,
                'track_lot' => true,
                'track_stock' => true,
                'min_stock_level' => 5.0,
                'default_cost' => 25000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Almond Milk',
                'sku' => 'INV-DAIRY-MILK-ALMOND',
                'category' => 'Dairy',
                'uom_id' => $uomL->id,
                'track_lot' => true,
                'track_stock' => true,
                'min_stock_level' => 3.0,
                'default_cost' => 45000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Whipped Cream',
                'sku' => 'INV-DAIRY-CREAM-WHIPPED',
                'category' => 'Dairy',
                'uom_id' => $uomMl->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0, // 500ml
                'default_cost' => 0.5, // per ml
                'status' => 'active',
            ],
            
            // Sweeteners & syrups
            [
                'tenant_id' => $tenantId,
                'name' => 'White Sugar',
                'sku' => 'INV-SWEET-SUGAR-WHITE',
                'category' => 'Sweetener',
                'uom_id' => $uomKg->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 5.0,
                'default_cost' => 15000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Brown Sugar',
                'sku' => 'INV-SWEET-SUGAR-BROWN',
                'category' => 'Sweetener',
                'uom_id' => $uomKg->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 2.0,
                'default_cost' => 18000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Vanilla Syrup',
                'sku' => 'INV-SWEET-SYRUP-VANILLA',
                'category' => 'Sweetener',
                'uom_id' => $uomMl->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 1000.0, // 1 liter
                'default_cost' => 0.08, // per ml
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Caramel Syrup',
                'sku' => 'INV-SWEET-SYRUP-CARAMEL',
                'category' => 'Sweetener',
                'uom_id' => $uomMl->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 1000.0,
                'default_cost' => 0.09,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Hazelnut Syrup',
                'sku' => 'INV-SWEET-SYRUP-HAZELNUT',
                'category' => 'Sweetener',
                'uom_id' => $uomMl->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0,
                'default_cost' => 0.10,
                'status' => 'active',
            ],
            
            // Tea
            [
                'tenant_id' => $tenantId,
                'name' => 'Green Tea Leaves',
                'sku' => 'INV-TEA-GREEN',
                'category' => 'Tea',
                'uom_id' => $uomG->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0, // 500g
                'default_cost' => 0.12, // per gram
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Black Tea Leaves',
                'sku' => 'INV-TEA-BLACK',
                'category' => 'Tea',
                'uom_id' => $uomG->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0,
                'default_cost' => 0.10,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Earl Grey Tea',
                'sku' => 'INV-TEA-EARL-GREY',
                'category' => 'Tea',
                'uom_id' => $uomG->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 300.0,
                'default_cost' => 0.15,
                'status' => 'active',
            ],
            
            // Other ingredients
            [
                'tenant_id' => $tenantId,
                'name' => 'Chocolate Powder',
                'sku' => 'INV-INGREDIENT-CHOCO-POWDER',
                'category' => 'Ingredient',
                'uom_id' => $uomKg->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 2.0,
                'default_cost' => 45000,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Matcha Powder',
                'sku' => 'INV-INGREDIENT-MATCHA',
                'category' => 'Ingredient',
                'uom_id' => $uomG->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 200.0, // 200g
                'default_cost' => 0.8, // per gram
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ice Cubes',
                'sku' => 'INV-INGREDIENT-ICE',
                'category' => 'Ingredient',
                'uom_id' => $uomKg->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 20.0, // 20 kg
                'default_cost' => 5000, // per kg
                'status' => 'active',
            ],
            
            // Toppings
            [
                'tenant_id' => $tenantId,
                'name' => 'Chocolate Chips',
                'sku' => 'INV-TOPPING-CHOCO-CHIPS',
                'category' => 'Topping',
                'uom_id' => $uomG->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0,
                'default_cost' => 0.2,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Caramel Drizzle',
                'sku' => 'INV-TOPPING-CARAMEL',
                'category' => 'Topping',
                'uom_id' => $uomMl->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0,
                'default_cost' => 0.15,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Cinnamon Powder',
                'sku' => 'INV-TOPPING-CINNAMON',
                'category' => 'Topping',
                'uom_id' => $uomG->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 200.0,
                'default_cost' => 0.3,
                'status' => 'active',
            ],
            
            // Paper cups & packaging
            [
                'tenant_id' => $tenantId,
                'name' => 'Paper Cup 12oz',
                'sku' => 'INV-PACKAGING-CUP-12OZ',
                'category' => 'Packaging',
                'uom_id' => $uomPcs->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0,
                'default_cost' => 1200, // per piece
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Paper Cup 16oz',
                'sku' => 'INV-PACKAGING-CUP-16OZ',
                'category' => 'Packaging',
                'uom_id' => $uomPcs->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 500.0,
                'default_cost' => 1500,
                'status' => 'active',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Paper Cup Lid',
                'sku' => 'INV-PACKAGING-CUP-LID',
                'category' => 'Packaging',
                'uom_id' => $uomPcs->id,
                'track_lot' => false,
                'track_stock' => true,
                'min_stock_level' => 1000.0,
                'default_cost' => 300,
                'status' => 'active',
            ],
        ];

        foreach ($inventoryItems as $itemData) {
            InventoryItem::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'sku' => $itemData['sku'],
                ],
                $itemData
            );
        }

        $this->command->info('✅ Coffee shop inventory items created successfully!');
    }
}

