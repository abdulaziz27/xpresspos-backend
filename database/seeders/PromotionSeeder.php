<?php

namespace Database\Seeders;

use App\Models\MemberTier;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionReward;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates realistic promotions with conditions and rewards for each tenant.
     */
    public function run(): void
    {
        // Get all tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('⚠️ No tenants found. Please run StoreSeeder first.');
            return;
        }

        // Create promotions for each tenant
        $tenants->each(function ($tenant) {
            // Check if tenant already has promotions
            $existingPromos = Promotion::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count();

            if ($existingPromos > 0) {
                $this->command->info("⏭️  Tenant '{$tenant->name}' already has {$existingPromos} promotion(s). Skipping...");
                return;
            }

            $this->command->info("🎁 Creating promotions for tenant: {$tenant->name}");

            // Get stores for this tenant
            $stores = Store::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
            $store = $stores->first();

            // Get products for this tenant
            $products = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', true)
                ->limit(5)
                ->get();

            // Get member tiers for this tenant
            $memberTiers = MemberTier::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->get();

            $goldTier = $memberTiers->where('slug', 'gold')->first();
            $silverTier = $memberTiers->where('slug', 'silver')->first();

            // Create realistic promotions
            $this->createMinSpendDiscountPromo($tenant, $store);
            $this->createBuyXGetYPromo($tenant, $store, $products);
            $this->createLoyaltyMultiplierPromo($tenant, $store);
            $this->createTierMemberDiscountPromo($tenant, $store, $goldTier);
            $this->createWeekendPromo($tenant, $store);
            $this->createHappyHourPromo($tenant, $store);

            $this->command->info("✅ Successfully created promotions for tenant: {$tenant->name}");
        });
    }

    /**
     * Create minimum spend discount promotion (MIN_SPEND + PCT_OFF)
     */
    protected function createMinSpendDiscountPromo(Tenant $tenant, ?Store $store): void
    {
        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'name' => 'Diskon 10% Minimal Belanja Rp 50.000',
            'description' => 'Dapatkan diskon 10% untuk pembelian minimal Rp 50.000. Berlaku untuk semua produk.',
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 10,
        ]);

        // Condition: Minimum spend
        PromotionCondition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'condition_type' => 'MIN_SPEND',
            'condition_value' => ['amount' => 50000],
        ]);

        // Reward: 10% discount
        PromotionReward::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => ['percentage' => 10],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create Buy X Get Y promotion (ITEM_INCLUDE + BUY_X_GET_Y)
     */
    protected function createBuyXGetYPromo(Tenant $tenant, ?Store $store, $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $product = $products->first();

        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'name' => 'Beli 2 Gratis 1 - ' . $product->name,
            'description' => "Beli 2 {$product->name}, dapatkan 1 gratis!",
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 20,
        ]);

        // Condition: Specific product
        PromotionCondition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'condition_type' => 'ITEM_INCLUDE',
            'condition_value' => ['product_ids' => [$product->id]],
        ]);

        // Reward: Buy 2 Get 1
        PromotionReward::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'reward_type' => 'BUY_X_GET_Y',
            'reward_value' => [
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'product_id' => $product->id, // Same product
            ],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create loyalty points multiplier promotion (POINTS_MULTIPLIER)
     */
    protected function createLoyaltyMultiplierPromo(Tenant $tenant, ?Store $store): void
    {
        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'name' => 'Double Points Weekend',
            'description' => 'Dapatkan 2x poin loyalty setiap akhir pekan. Berlaku Sabtu & Minggu.',
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => true,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 15,
        ]);

        // Condition: Day of week (Saturday & Sunday)
        PromotionCondition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'condition_type' => 'DOW',
            'condition_value' => ['days' => [6, 0]], // Saturday (6) and Sunday (0)
        ]);

        // Reward: 2x points multiplier
        PromotionReward::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'reward_type' => 'POINTS_MULTIPLIER',
            'reward_value' => ['multiplier' => 2.0],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create tier member discount promotion (CUSTOMER_TIER_IN + PCT_OFF)
     */
    protected function createTierMemberDiscountPromo(Tenant $tenant, ?Store $store, ?MemberTier $tier): void
    {
        if (!$tier) {
            return;
        }

        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'name' => 'Diskon Khusus Member ' . $tier->name,
            'description' => "Diskon tambahan 5% khusus untuk member tier {$tier->name}.",
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => true,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(2),
            'priority' => 5,
        ]);

        // Condition: Customer tier
        PromotionCondition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'condition_type' => 'CUSTOMER_TIER_IN',
            'condition_value' => ['tier_ids' => [$tier->id]],
        ]);

        // Reward: 5% discount
        PromotionReward::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => ['percentage' => 5],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create weekend discount promotion (DOW + PCT_OFF)
     */
    protected function createWeekendPromo(Tenant $tenant, ?Store $store): void
    {
        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'name' => 'Weekend Special - Diskon 15%',
            'description' => 'Diskon 15% khusus akhir pekan. Berlaku Sabtu & Minggu.',
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 25,
        ]);

        // Condition: Day of week (Saturday & Sunday)
        PromotionCondition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'condition_type' => 'DOW',
            'condition_value' => ['days' => [6, 0]], // Saturday (6) and Sunday (0)
        ]);

        // Reward: 15% discount
        PromotionReward::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => ['percentage' => 15],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create happy hour promotion (TIME_RANGE + AMOUNT_OFF)
     */
    protected function createHappyHourPromo(Tenant $tenant, ?Store $store): void
    {
        $promotion = Promotion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store?->id,
            'name' => 'Happy Hour - Potongan Rp 5.000',
            'description' => 'Potongan Rp 5.000 setiap hari pukul 14:00 - 17:00. Berlaku setiap hari.',
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 30,
        ]);

        // Condition: Time range (14:00 - 17:00)
        PromotionCondition::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'condition_type' => 'TIME_RANGE',
            'condition_value' => [
                'start_time' => '14:00',
                'end_time' => '17:00',
            ],
        ]);

        // Reward: Fixed amount discount
        PromotionReward::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promotion->id,
            'reward_type' => 'AMOUNT_OFF',
            'reward_value' => ['amount' => 5000],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }
}

