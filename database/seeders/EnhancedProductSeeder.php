<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class EnhancedProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $storeId = config('demo.store_id') ?? \App\Models\Store::first()->id;
        $categories = Category::query()->withoutStoreScope()->where('store_id', $storeId)->get();

        // Get category IDs safely
        $coffeeCategory = $categories->where('slug', 'coffee')->first();
        $teaCategory = $categories->where('slug', 'tea')->first();
        $pastryCategory = $categories->where('slug', 'pastry')->first();
        $snacksCategory = $categories->where('slug', 'snacks')->first();

        if (!$coffeeCategory || !$teaCategory || !$pastryCategory || !$snacksCategory) {
            $this->command->error('Required categories not found. Make sure CategorySeeder runs first.');
            return;
        }

        $additionalProducts = [
            // More Coffee products
            [
                'store_id' => $storeId,
                'category_id' => $coffeeCategory->id,
                'name' => 'Americano',
                'sku' => 'AME001',
                'description' => 'Espresso with hot water',
                'price' => 22000,
                'cost_price' => 10000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 4,
                'is_favorite' => true,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $coffeeCategory->id,
                'name' => 'Mocha',
                'sku' => 'MOC001',
                'description' => 'Espresso with chocolate and steamed milk',
                'price' => 32000,
                'cost_price' => 16000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 5,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $coffeeCategory->id,
                'name' => 'Macchiato',
                'sku' => 'MAC001',
                'description' => 'Espresso with a dollop of foamed milk',
                'price' => 26000,
                'cost_price' => 13000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 6,
            ],
            // More Tea products
            [
                'store_id' => $storeId,
                'category_id' => $teaCategory->id,
                'name' => 'Jasmine Tea',
                'sku' => 'JAS001',
                'description' => 'Fragrant jasmine green tea',
                'price' => 20000,
                'cost_price' => 10000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 3,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $teaCategory->id,
                'name' => 'Chamomile Tea',
                'sku' => 'CHA001',
                'description' => 'Relaxing chamomile herbal tea',
                'price' => 18000,
                'cost_price' => 9000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 4,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $teaCategory->id,
                'name' => 'Oolong Tea',
                'sku' => 'OOL001',
                'description' => 'Traditional oolong tea',
                'price' => 22000,
                'cost_price' => 11000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 5,
                'is_favorite' => true,
            ],
            // More Pastry products
            [
                'store_id' => $storeId,
                'category_id' => $pastryCategory->id,
                'name' => 'Danish Pastry',
                'sku' => 'DAN001',
                'description' => 'Flaky Danish pastry with fruit filling',
                'price' => 20000,
                'cost_price' => 10000,
                'track_inventory' => true,
                'stock' => 12,
                'min_stock_level' => 3,
                'status' => true,
                'sort_order' => 3,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $pastryCategory->id,
                'name' => 'Cinnamon Roll',
                'sku' => 'CIN001',
                'description' => 'Sweet cinnamon roll with glaze',
                'price' => 16000,
                'cost_price' => 8000,
                'track_inventory' => true,
                'stock' => 8,
                'min_stock_level' => 2,
                'status' => true,
                'sort_order' => 4,
                'is_favorite' => true,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $pastryCategory->id,
                'name' => 'Blueberry Scone',
                'sku' => 'SCO001',
                'description' => 'Fresh blueberry scone',
                'price' => 18000,
                'cost_price' => 9000,
                'track_inventory' => true,
                'stock' => 6,
                'min_stock_level' => 2,
                'status' => true,
                'sort_order' => 5,
            ],
            // Snacks products
            [
                'store_id' => $storeId,
                'category_id' => $snacksCategory->id,
                'name' => 'Chocolate Chip Cookie',
                'sku' => 'COO001',
                'description' => 'Homemade chocolate chip cookie',
                'price' => 12000,
                'cost_price' => 6000,
                'track_inventory' => true,
                'stock' => 25,
                'min_stock_level' => 5,
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $snacksCategory->id,
                'name' => 'Banana Bread',
                'sku' => 'BAN001',
                'description' => 'Moist banana bread slice',
                'price' => 15000,
                'cost_price' => 7500,
                'track_inventory' => true,
                'stock' => 10,
                'min_stock_level' => 3,
                'status' => true,
                'sort_order' => 2,
                'is_favorite' => true,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $snacksCategory->id,
                'name' => 'Granola Bar',
                'sku' => 'GRA001',
                'description' => 'Healthy granola bar with nuts',
                'price' => 10000,
                'cost_price' => 5000,
                'track_inventory' => true,
                'stock' => 30,
                'min_stock_level' => 8,
                'status' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($additionalProducts as $product) {
            Product::query()->withoutStoreScope()->updateOrCreate(
                [
                    'store_id' => $product['store_id'],
                    'sku' => $product['sku']
                ],
                $product
            );
        }

        $this->command->info('Created ' . count($additionalProducts) . ' additional products');
        
        // Create variants for new products
        $this->createVariantsForNewProducts($storeId);
    }

    private function createVariantsForNewProducts($storeId): void
    {
        $products = Product::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->get()
            ->keyBy('sku');

        // Americano variants
        if ($products->has('AME001')) {
            $this->createVariants($products['AME001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 8000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
                ['name' => 'Temperature', 'value' => 'Iced', 'price_adjustment' => 3000],
            ]);
        }

        // Mocha variants
        if ($products->has('MOC001')) {
            $this->createVariants($products['MOC001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 8000],
                ['name' => 'Milk', 'value' => 'Regular Milk', 'price_adjustment' => 0],
                ['name' => 'Milk', 'value' => 'Oat Milk', 'price_adjustment' => 8000],
                ['name' => 'Extra', 'value' => 'Extra Chocolate', 'price_adjustment' => 5000],
                ['name' => 'Extra', 'value' => 'Whipped Cream', 'price_adjustment' => 5000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
                ['name' => 'Temperature', 'value' => 'Iced', 'price_adjustment' => 3000],
            ]);
        }

        // Cookie variants
        if ($products->has('COO001')) {
            $this->createVariants($products['COO001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 3000],
                ['name' => 'Extra', 'value' => 'Extra Chocolate', 'price_adjustment' => 2000],
            ]);
        }

        $this->command->info('Created variants for new products');
    }

    private function createVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            ProductVariant::withoutGlobalScopes()->updateOrCreate(
                [
                    'store_id' => $product->store_id,
                    'product_id' => $product->id,
                    'name' => $variantData['name'],
                    'value' => $variantData['value'],
                ],
                [
                    'price_adjustment' => $variantData['price_adjustment'],
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
        }
    }
}