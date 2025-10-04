<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $storeId = config('demo.store_id') ?? \App\Models\Store::first()->id;
        $categories = \App\Models\Category::withoutStoreScope()->where('store_id', $storeId)->get();
        
        // Get category IDs safely
        $coffeeCategory = $categories->where('slug', 'coffee')->first();
        $teaCategory = $categories->where('slug', 'tea')->first();
        $pastryCategory = $categories->where('slug', 'pastry')->first();
        $snacksCategory = $categories->where('slug', 'snacks')->first();
        
        if (!$coffeeCategory || !$teaCategory || !$pastryCategory || !$snacksCategory) {
            $this->command->error('Required categories not found. Make sure CategorySeeder runs first.');
            $this->command->info('Store ID: ' . $storeId);
            $this->command->info('Categories found: ' . $categories->count());
            $this->command->info('Category names: ' . $categories->pluck('name')->implode(', '));
            $this->command->info('Category slugs: ' . $categories->pluck('slug')->implode(', '));
            return;
        }
        
        $products = [
            // Coffee products
            [
                'store_id' => $storeId,
                'category_id' => $coffeeCategory->id,
                'name' => 'Espresso',
                'sku' => 'ESP001',
                'description' => 'Strong black coffee',
                'price' => 15000,
                'cost_price' => 8000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $coffeeCategory->id,
                'name' => 'Cappuccino',
                'sku' => 'CAP001',
                'description' => 'Espresso with steamed milk foam',
                'price' => 25000,
                'cost_price' => 12000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 2,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $coffeeCategory->id,
                'name' => 'Latte',
                'sku' => 'LAT001',
                'description' => 'Espresso with steamed milk',
                'price' => 28000,
                'cost_price' => 14000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 3,
            ],
            // Tea products
            [
                'store_id' => $storeId,
                'category_id' => $teaCategory->id,
                'name' => 'Green Tea',
                'sku' => 'GT001',
                'description' => 'Fresh green tea',
                'price' => 18000,
                'cost_price' => 9000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $teaCategory->id,
                'name' => 'Earl Grey',
                'sku' => 'EG001',
                'description' => 'Classic Earl Grey tea',
                'price' => 20000,
                'cost_price' => 10000,
                'track_inventory' => false,
                'stock' => 0,
                'status' => true,
                'sort_order' => 2,
            ],
            // Pastry products
            [
                'store_id' => $storeId,
                'category_id' => $pastryCategory->id,
                'name' => 'Croissant',
                'sku' => 'CRO001',
                'description' => 'Buttery croissant',
                'price' => 15000,
                'cost_price' => 7000,
                'track_inventory' => true,
                'stock' => 20,
                'min_stock_level' => 5,
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'store_id' => $storeId,
                'category_id' => $pastryCategory->id,
                'name' => 'Chocolate Muffin',
                'sku' => 'MUF001',
                'description' => 'Rich chocolate muffin',
                'price' => 18000,
                'cost_price' => 9000,
                'track_inventory' => true,
                'stock' => 15,
                'min_stock_level' => 3,
                'status' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($products as $product) {
            \App\Models\Product::withoutStoreScope()->create($product);
        }
    }
}
