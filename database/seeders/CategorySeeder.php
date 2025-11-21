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
        $store = \App\Models\Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        $categories = [
            [
                'tenant_id' => $tenantId,
                'name' => 'Coffee',
                'slug' => 'coffee',
                'description' => 'Various coffee drinks',
                'status' => true,
                'sort_order' => 1,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tea',
                'slug' => 'tea',
                'description' => 'Tea and herbal drinks',
                'status' => true,
                'sort_order' => 2,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Pastry',
                'slug' => 'pastry',
                'description' => 'Fresh baked goods',
                'status' => true,
                'sort_order' => 3,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Snacks',
                'slug' => 'snacks',
                'description' => 'Light snacks and appetizers',
                'status' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::query()->withoutTenantScope()->create($category);
        }
    }
}
