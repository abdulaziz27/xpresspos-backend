<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Promotion;
use App\Models\Voucher;

class CoffeeShopVoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic but minimal vouchers for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;

        // Check if vouchers already exist
        $existingVouchers = Voucher::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingVouchers > 0) {
            $this->command->info("⏭️  Tenant already has {$existingVouchers} voucher(s). Skipping...");
            return;
        }

        $this->command->info("🎫 Creating vouchers for tenant: {$tenant->name}");

        // Get a promotion to link voucher to (optional)
        $promotion = Promotion::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'CODED')
            ->first();

        // 1. Welcome Voucher - 20% off for new customers
        $this->createVoucher(
            $tenantId,
            'WELCOME20',
            'Voucher Selamat Datang - Diskon 20%',
            now()->startOfMonth(),
            now()->endOfMonth()->addMonths(2),
            100, // Max 100 redemptions
            $promotion
        );

        // 2. Loyalty Voucher - 15% off
        $this->createVoucher(
            $tenantId,
            'LOYALTY15',
            'Voucher Loyalty - Diskon 15%',
            now()->startOfMonth(),
            now()->endOfMonth()->addMonths(1),
            50, // Max 50 redemptions
            $promotion
        );

        // 3. Special Voucher - Rp 10.000 off
        $this->createVoucher(
            $tenantId,
            'SPECIAL10K',
            'Voucher Spesial - Potongan Rp 10.000',
            now()->startOfMonth(),
            now()->endOfMonth()->addMonths(1),
            30, // Max 30 redemptions
            $promotion
        );

        $this->command->info("✅ Successfully created vouchers for tenant: {$tenant->name}");
    }

    /**
     * Create a voucher
     */
    protected function createVoucher(
        string $tenantId,
        string $code,
        string $description,
        $validFrom,
        $validUntil,
        ?int $maxRedemptions = null,
        ?Promotion $promotion = null
    ): void {
        $voucher = Voucher::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'promotion_id' => $promotion?->id,
            'code' => $code,
            'max_redemptions' => $maxRedemptions,
            'redemptions_count' => 0,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'status' => 'active',
        ]);

        $this->command->line("   ✓ Created voucher: {$code} - {$description}");
    }
}

