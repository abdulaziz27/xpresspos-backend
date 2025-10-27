<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for small businesses just getting started with essential POS features',
                'price' => 99000,
                'annual_price' => 990000,
                'features' => [
                    'pos',
                    'basic_reports',
                    'customer_management',
                    'member_management',
                ],
                'limits' => [
                    'products' => 20,
                    'users' => 2,
                    'outlets' => 1,
                    'transactions' => 12000, // Annual limit with soft cap
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Advanced features for growing businesses with inventory management',
                'price' => 199000,
                'annual_price' => 1990000,
                'features' => [
                    'pos',
                    'basic_reports',
                    'advanced_reports',
                    'customer_management',
                    'member_management',
                    'inventory_tracking',
                    'cogs_calculation',
                    'monthly_email_reports',
                    'report_export',
                ],
                'limits' => [
                    'products' => 300,
                    'users' => 10,
                    'outlets' => 1,
                    'transactions' => 120000, // Annual limit with soft cap
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Complete solution for large businesses with unlimited features',
                'price' => 399000,
                'annual_price' => 3990000,
                'features' => [
                    'pos',
                    'basic_reports',
                    'advanced_reports',
                    'customer_management',
                    'member_management',
                    'inventory_tracking',
                    'cogs_calculation',
                    'monthly_email_reports',
                    'report_export',
                    'advanced_analytics',
                    'multi_outlet',
                    'api_access',
                    'priority_support',
                ],
                'limits' => [
                    'products' => null, // Unlimited
                    'users' => null, // Unlimited
                    'outlets' => null, // Unlimited
                    'transactions' => null, // Unlimited
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            \App\Models\Plan::create($plan);
        }
    }
}
