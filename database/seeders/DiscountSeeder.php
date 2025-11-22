<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $store = \App\Models\Store::first();
        if (!$store) {
            $this->command->warn('No store found. Skipping discount seeding.');
            return;
        }
        
        $storeId = $store->id;
        $tenantId = $store->tenant_id;
        
        $discounts = [
            [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'name' => 'Welcome New Customer',
                'description' => 'Discount for new customers',
                'type' => 'percentage',
                'value' => 20,
                'status' => 'active',
                'expired_date' => '2025-12-31'
            ],
            [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'name' => 'Happy Hour',
                'description' => 'Discount during happy hour',
                'type' => 'percentage',
                'value' => 15,
                'status' => 'active',
                'expired_date' => '2025-12-31'
            ],
            [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'name' => 'Student Discount',
                'description' => 'Special discount for students',
                'type' => 'percentage',
                'value' => 10,
                'status' => 'active',
                'expired_date' => '2025-12-31'
            ],
        ];

        foreach ($discounts as $discount) {
            \App\Models\Discount::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'store_id' => $discount['store_id'],
                    'name' => $discount['name'],
                ],
                $discount
            );
        }
    }
}
