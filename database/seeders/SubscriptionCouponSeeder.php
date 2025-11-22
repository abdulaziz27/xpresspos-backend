<?php

namespace Database\Seeders;

use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Models\Voucher;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionCouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates sample subscription coupons for testing.
     */
    public function run(): void
    {
        // Get first tenant or create a global one (null tenant_id for global coupons)
        $tenant = Tenant::first();
        
        if (!$tenant) {
            $this->command->warn('No tenant found. Please create a tenant first.');
            return;
        }

        // Create promotion for 10% discount
        $promo10 = Promotion::create([
            'tenant_id' => $tenant->id,
            'store_id' => null, // Global promotion
            'name' => 'Welcome Discount 10%',
            'description' => 'Diskon 10% untuk subscription baru',
            'type' => 'CODED',
            'code' => null, // Voucher will have the code
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(), // Valid for 1 year
            'priority' => 10,
        ]);

        // Create reward for 10% discount
        PromotionReward::create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promo10->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => [
                'percentage' => 10,
            ],
        ]);

        // Create voucher with code WELCOME10
        Voucher::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promo10->id,
            'code' => 'WELCOME10',
            'max_redemptions' => 100, // Can be used 100 times
            'redemptions_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'status' => 'active',
        ]);

        // Create promotion for 15% discount
        $promo15 = Promotion::create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
            'name' => 'New User Discount 15%',
            'description' => 'Diskon 15% untuk pengguna baru',
            'type' => 'CODED',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'priority' => 15,
        ]);

        PromotionReward::create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promo15->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => [
                'percentage' => 15,
            ],
        ]);

        Voucher::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promo15->id,
            'code' => 'NEWUSER15',
            'max_redemptions' => 50,
            'redemptions_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'status' => 'active',
        ]);

        // Create promotion for fixed amount discount (Rp 50,000)
        $promoFixed = Promotion::create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
            'name' => 'Fixed Discount Rp 50,000',
            'description' => 'Diskon tetap Rp 50,000 untuk subscription',
            'type' => 'CODED',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'priority' => 20,
        ]);

        PromotionReward::create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promoFixed->id,
            'reward_type' => 'AMOUNT_OFF',
            'reward_value' => [
                'amount' => 50000,
            ],
        ]);

        Voucher::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promoFixed->id,
            'code' => 'DISKON50K',
            'max_redemptions' => 30,
            'redemptions_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
            'status' => 'active',
        ]);

        // Create promotion for 20% discount (limited)
        $promo20 = Promotion::create([
            'tenant_id' => $tenant->id,
            'store_id' => null,
            'name' => 'Special Discount 20%',
            'description' => 'Diskon spesial 20% untuk subscription',
            'type' => 'CODED',
            'code' => null,
            'stackable' => false,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(6), // Valid for 6 months
            'priority' => 25,
        ]);

        PromotionReward::create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promo20->id,
            'reward_type' => 'PCT_OFF',
            'reward_value' => [
                'percentage' => 20,
            ],
        ]);

        Voucher::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'promotion_id' => $promo20->id,
            'code' => 'SPECIAL20',
            'max_redemptions' => 20, // Limited to 20 uses
            'redemptions_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(6),
            'status' => 'active',
        ]);

        $this->command->info('Subscription coupons created successfully!');
        $this->command->info('Available coupon codes:');
        $this->command->info('  - WELCOME10 (10% discount)');
        $this->command->info('  - NEWUSER15 (15% discount)');
        $this->command->info('  - DISKON50K (Rp 50,000 discount)');
        $this->command->info('  - SPECIAL20 (20% discount)');
    }
}




