<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionReward;
use App\Models\Product;
use App\Models\MemberTier;

class CoffeeShopPromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic but minimal promotions for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;

        // Check if promotions already exist
        $existingPromos = Promotion::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingPromos > 0) {
            $this->command->info("⏭️  Tenant already has {$existingPromos} promotion(s). Skipping...");
            return;
        }

        $this->command->info("🎁 Creating promotions for tenant: {$tenant->name}");

        // Get products and tiers for promotions
        $products = Product::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', true)
            ->limit(3)
            ->get();

        $goldTier = MemberTier::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('slug', 'gold')
            ->first();

        // 1. Min Spend Discount - Simple and effective
        $this->createMinSpendPromo($tenantId);

        // 2. Weekend Special - For weekends only
        $this->createWeekendPromo($tenantId);

        // 3. Buy X Get Y - If products available
        if ($products->isNotEmpty()) {
            $this->createBuyXGetYPromo($tenantId, $products->first());
        }

        // 4. Gold Member Bonus - If tier available
        if ($goldTier) {
            $this->createGoldMemberPromo($tenantId, $goldTier);
        }

        $this->command->info("✅ Successfully created promotions for tenant: {$tenant->name}");
    }

    /**
     * Create minimum spend discount promotion
     */
    protected function createMinSpendPromo(string $tenantId): void
    {
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'store_id' => null, // All stores
            'name' => 'Diskon 10% Min. Belanja Rp 50.000',
            'description' => 'Dapatkan diskon 10% untuk pembelian minimal Rp 50.000',
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 10,
        ]);

        PromotionCondition::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'condition_type' => 'MIN_SPEND',
            'condition_value' => ['amount' => 50000],
        ]);

        PromotionReward::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => ['percentage' => 10],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create weekend special promotion
     */
    protected function createWeekendPromo(string $tenantId): void
    {
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'store_id' => null, // All stores
            'name' => 'Weekend Special - Diskon 15%',
            'description' => 'Diskon 15% khusus akhir pekan (Sabtu & Minggu)',
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 20,
        ]);

        PromotionCondition::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'condition_type' => 'DOW',
            'condition_value' => ['days' => [6, 0]], // Saturday (6) and Sunday (0)
        ]);

        PromotionReward::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => ['percentage' => 15],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create Buy X Get Y promotion
     */
    protected function createBuyXGetYPromo(string $tenantId, Product $product): void
    {
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'store_id' => null, // All stores
            'name' => 'Beli 2 Gratis 1 - ' . $product->name,
            'description' => "Beli 2 {$product->name}, dapatkan 1 gratis!",
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(1),
            'priority' => 15,
        ]);

        PromotionCondition::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'condition_type' => 'ITEM_INCLUDE',
            'condition_value' => ['product_ids' => [$product->id]],
        ]);

        PromotionReward::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'reward_type' => 'BUY_X_GET_Y',
            'reward_value' => [
                'buy_quantity' => 2,
                'get_quantity' => 1,
                'product_id' => $product->id,
            ],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }

    /**
     * Create Gold Member bonus promotion
     */
    protected function createGoldMemberPromo(string $tenantId, MemberTier $tier): void
    {
        $promotion = Promotion::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'store_id' => null, // All stores
            'name' => 'Bonus Member ' . $tier->name,
            'description' => "Diskon tambahan 5% khusus member tier {$tier->name}",
            'type' => 'AUTOMATIC',
            'code' => null,
            'stackable' => true,
            'status' => 'active',
            'starts_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth()->addMonths(2),
            'priority' => 5,
        ]);

        PromotionCondition::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'condition_type' => 'CUSTOMER_TIER_IN',
            'condition_value' => ['tier_ids' => [$tier->id]],
        ]);

        PromotionReward::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => ['percentage' => 5],
        ]);

        $this->command->line("   ✓ Created: {$promotion->name}");
    }
}

