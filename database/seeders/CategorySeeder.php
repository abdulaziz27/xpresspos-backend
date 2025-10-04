<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $storeId = config('demo.store_id') ?? \App\Models\Store::first()->id;
        
        $categories = [
            [
                'store_id' => $storeId,
                'name' => 'Coffee',
                'slug' => 'coffee',
                'description' => 'Various coffee drinks',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'store_id' => $storeId,
                'name' => 'Tea',
                'slug' => 'tea',
                'description' => 'Tea and herbal drinks',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'store_id' => $storeId,
                'name' => 'Pastry',
                'slug' => 'pastry',
                'description' => 'Fresh baked goods',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'store_id' => $storeId,
                'name' => 'Snacks',
                'slug' => 'snacks',
                'description' => 'Light snacks and appetizers',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::withoutStoreScope()->create($category);
        }
    }
}
