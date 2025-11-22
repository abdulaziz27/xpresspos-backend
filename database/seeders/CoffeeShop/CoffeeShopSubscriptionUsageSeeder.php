<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Models\PlanFeature;

class CoffeeShopSubscriptionUsageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic subscription usage tracking for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Get active subscription
        $subscription = Subscription::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            $this->command->warn('No active subscription found. Skipping usage tracking.');
            return;
        }

        // Check if usage already exists
        $existingUsage = SubscriptionUsage::where('subscription_id', $subscription->id)->count();

        if ($existingUsage > 0) {
            $this->command->info("⏭️  Subscription already has {$existingUsage} usage record(s). Skipping...");
            return;
        }

        $this->command->info("📊 Creating subscription usage for tenant: {$tenant->name}");

        // Get plan features that have limits
        $plan = $subscription->plan;
        $planFeatures = PlanFeature::where('plan_id', $plan->id)
            ->where('is_enabled', true)
            ->where('feature_code', 'like', 'MAX_%')
            ->get();

        if ($planFeatures->isEmpty()) {
            $this->command->warn('No plan features with limits found. Skipping usage tracking.');
            return;
        }

        // Map feature codes to feature types
        $featureTypeMap = [
            'MAX_TRANSACTIONS_PER_YEAR' => 'transactions',
            'MAX_PRODUCTS' => 'products',
            'MAX_STORES' => 'stores',
            'MAX_STAFF' => 'staff',
        ];

        $subscriptionYearStart = $subscription->starts_at->copy()->startOfYear();
        $subscriptionYearEnd = $subscription->starts_at->copy()->endOfYear();

        foreach ($planFeatures as $feature) {
            $featureType = $featureTypeMap[$feature->feature_code] ?? strtolower(str_replace('MAX_', '', $feature->feature_code));
            $limitValue = (int) $feature->limit_value;
            
            // Skip if unlimited (-1) or no limit
            if ($limitValue <= 0) {
                continue;
            }

            // Calculate current usage based on actual data
            $currentUsage = 0;
            
            if ($featureType === 'transactions') {
                // Count orders/payments for this year
                $currentUsage = \App\Models\Order::query()->withoutGlobalScopes()
                    ->join('stores', 'orders.store_id', '=', 'stores.id')
                    ->where('stores.tenant_id', $tenantId)
                    ->whereYear('orders.created_at', $subscription->starts_at->year)
                    ->count();
            } elseif ($featureType === 'products') {
                $currentUsage = \App\Models\Product::query()->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', true)
                    ->count();
            } elseif ($featureType === 'stores') {
                $currentUsage = \App\Models\Store::where('tenant_id', $tenantId)->count();
            } elseif ($featureType === 'staff') {
                $currentUsage = \App\Models\User::whereHas('storeUserAssignments', function ($q) use ($tenantId) {
                    $q->whereHas('store', function ($storeQ) use ($tenantId) {
                        $storeQ->where('tenant_id', $tenantId);
                    });
                })->count();
            }

            // Adjust usage to be realistic (50-80% of limit for demo)
            $usagePercentage = rand(50, 80) / 100;
            $currentUsage = min($currentUsage, (int) ($limitValue * $usagePercentage));

            // Check if soft cap should be triggered (80% of quota)
            $softCapTriggered = ($currentUsage / $limitValue) >= 0.8;

            SubscriptionUsage::create([
                'subscription_id' => $subscription->id,
                'feature_type' => $featureType,
                'current_usage' => $currentUsage,
                'annual_quota' => $limitValue,
                'subscription_year_start' => $subscriptionYearStart,
                'subscription_year_end' => $subscriptionYearEnd,
                'soft_cap_triggered' => $softCapTriggered,
                'soft_cap_triggered_at' => $softCapTriggered ? now()->subDays(rand(1, 10)) : null,
            ]);

            $usagePercent = round(($currentUsage / $limitValue) * 100, 1);
            $this->command->line("   ✓ Created usage tracking for {$featureType}: {$currentUsage}/{$limitValue} ({$usagePercent}%)");
        }

        $this->command->info("✅ Successfully created subscription usage for tenant: {$tenant->name}");
    }
}

