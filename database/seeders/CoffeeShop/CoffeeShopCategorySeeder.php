<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Store;

class CoffeeShopCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic coffee shop categories.
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        $categories = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Espresso',
                'slug' => 'espresso',
                'description' => 'Espresso dan varian kopi berbasis espresso',
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Coffee',
                'slug' => 'coffee',
                'description' => 'Kopi manual brew dan filtered coffee',
                'status' => true,
                'sort_order' => 2,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tea',
                'slug' => 'tea',
                'description' => 'Teh panas dan dingin',
                'status' => true,
                'sort_order' => 3,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Non-Coffee',
                'slug' => 'non-coffee',
                'description' => 'Minuman non-kopi (chocolate, matcha, dll)',
                'status' => true,
                'sort_order' => 4,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Pastry',
                'slug' => 'pastry',
                'description' => 'Roti dan pastry segar',
                'status' => true,
                'sort_order' => 5,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Snacks',
                'slug' => 'snacks',
                'description' => 'Camilan dan snack',
                'status' => true,
                'sort_order' => 6,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Meals',
                'slug' => 'meals',
                'description' => 'Makanan berat',
                'status' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'slug' => $categoryData['slug'],
                ],
                $categoryData
            );
        }

        $this->command->info('✅ Coffee shop categories created successfully!');
    }
}

