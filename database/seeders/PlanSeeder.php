<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;
use App\Models\PlanFeature;

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
                'price' => 69000,
                'annual_price' => 690000,
                'features' => [
                    'pos',
                    'basic_reports',
                    'customer_management',
                    'member_management',
                ],
                'limits' => [
                    // LEGACY: JSON limits untuk backward compatibility saja
                    // SUMBER KEBENARAN: plan_features table (yang digunakan oleh PlanLimitService)
                    // Nilai di sini hanya untuk backward compatibility, tidak digunakan oleh service layer
                    'products' => 20,
                    'users' => 2,
                    'outlets' => 1,
                    'transactions' => 12000,
                ],
                'is_active' => true,
                'sort_order' => 1,
                // SUMBER KEBENARAN: Plan features untuk PlanLimitService
                // Ini yang digunakan oleh service layer untuk validasi limit dan feature flags
                'plan_features' => [
                    // Hard Limits (MAX_*)
                    ['feature_code' => 'MAX_STORES', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'MAX_PRODUCTS', 'limit_value' => '20', 'is_enabled' => true],
                    ['feature_code' => 'MAX_STAFF', 'limit_value' => '2', 'is_enabled' => true],
                    ['feature_code' => 'MAX_TRANSACTIONS_PER_YEAR', 'limit_value' => '12000', 'is_enabled' => true],
                    ['feature_code' => 'MAX_TRANSACTIONS_PER_MONTH', 'limit_value' => '1000', 'is_enabled' => true],
                    // Feature Flags (ALLOW_*)
                    ['feature_code' => 'ALLOW_PROMO', 'limit_value' => '0', 'is_enabled' => false], // No promo for Basic
                    ['feature_code' => 'ALLOW_LOYALTY', 'limit_value' => '1', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_MULTI_STORE', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_API_ACCESS', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_INVENTORY', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_ADVANCED_REPORTS', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_REPORT_EXPORT', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_MONTHLY_EMAIL_REPORTS', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_AI_ANALYTICS', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_COGS_CALCULATION', 'limit_value' => '0', 'is_enabled' => false],
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Advanced features for growing businesses with inventory management',
                'price' => 159000,
                'annual_price' => 1590000, // 10x monthly price
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
                    // LEGACY: JSON limits untuk backward compatibility saja
                    // SUMBER KEBENARAN: plan_features table
                    'products' => 300,
                    'users' => 10,
                    'outlets' => 1,
                    'transactions' => 120000,
                ],
                'is_active' => true,
                'sort_order' => 2,
                // Plan features untuk PlanLimitService
                'plan_features' => [
                    // Hard Limits (MAX_*)
                    ['feature_code' => 'MAX_STORES', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'MAX_PRODUCTS', 'limit_value' => '300', 'is_enabled' => true],
                    ['feature_code' => 'MAX_STAFF', 'limit_value' => '10', 'is_enabled' => true],
                    ['feature_code' => 'MAX_TRANSACTIONS_PER_YEAR', 'limit_value' => '120000', 'is_enabled' => true],
                    ['feature_code' => 'MAX_TRANSACTIONS_PER_MONTH', 'limit_value' => '10000', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_INVENTORY', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_PROMO', 'limit_value' => '1', 'is_enabled' => true], // Yes promo for Pro
                    // Feature Flags (ALLOW_*)
                    ['feature_code' => 'ALLOW_LOYALTY', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_MULTI_STORE', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_API_ACCESS', 'limit_value' => '0', 'is_enabled' => false],
                    ['feature_code' => 'ALLOW_ADVANCED_REPORTS', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_REPORT_EXPORT', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_MONTHLY_EMAIL_REPORTS', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_COGS_CALCULATION', 'limit_value' => '1', 'is_enabled' => true],
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Complete solution for large businesses with unlimited features',
                'price' => 599000,
                'annual_price' => 5990000, // 10x monthly price
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
                    // LEGACY: JSON limits untuk backward compatibility saja
                    // SUMBER KEBENARAN: plan_features table
                    'products' => 500,
                    'users' => 25,
                    'outlets' => 3,
                    'transactions' => 180000,
                ],
                'is_active' => true,
                'sort_order' => 3,
                // SUMBER KEBENARAN: Plan features untuk PlanLimitService
                'plan_features' => [
                    // Hard Limits (MAX_*) - Unlimited = -1
                    ['feature_code' => 'MAX_STORES', 'limit_value' => '3', 'is_enabled' => true],
                    ['feature_code' => 'MAX_PRODUCTS', 'limit_value' => '500', 'is_enabled' => true],
                    ['feature_code' => 'MAX_STAFF', 'limit_value' => '25', 'is_enabled' => true],
                    ['feature_code' => 'MAX_TRANSACTIONS_PER_YEAR', 'limit_value' => '180000', 'is_enabled' => true],
                    ['feature_code' => 'MAX_TRANSACTIONS_PER_MONTH', 'limit_value' => '15000', 'is_enabled' => true],
                    // Feature Flags (ALLOW_*)
                    ['feature_code' => 'ALLOW_PROMO', 'limit_value' => '1', 'is_enabled' => true], // Yes promo for Enterprise
                    ['feature_code' => 'ALLOW_LOYALTY', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_MULTI_STORE', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_API_ACCESS', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_TABLE_MANAGEMENT', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_INVENTORY', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_PAYMENT_GATEWAY', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_ADVANCED_REPORTS', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_REPORT_EXPORT', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_MONTHLY_EMAIL_REPORTS', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_AI_ANALYTICS', 'limit_value' => '1', 'is_enabled' => true],
                    ['feature_code' => 'ALLOW_COGS_CALCULATION', 'limit_value' => '1', 'is_enabled' => true],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            // Extract plan_features before creating plan
            $planFeatures = $planData['plan_features'] ?? [];
            unset($planData['plan_features']);

            // Create or update plan (idempotent)
            $plan = Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );

            // Create or update plan features (idempotent)
            foreach ($planFeatures as $featureData) {
                PlanFeature::updateOrCreate(
                    [
                    'plan_id' => $plan->id,
                    'feature_code' => $featureData['feature_code'],
                    ],
                    [
                    'limit_value' => $featureData['limit_value'],
                    'is_enabled' => $featureData['is_enabled'],
                    ]
                );
            }

            $this->command->info("✅ Created/Updated plan: {$plan->name} with " . count($planFeatures) . " features");
        }
    }
}
