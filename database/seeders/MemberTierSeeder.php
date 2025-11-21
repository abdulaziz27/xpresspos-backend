<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MemberTierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $loyaltyService = app(\App\Services\LoyaltyService::class);
        
        // Initialize default tiers for all existing tenants
        \App\Models\Tenant::all()->each(function ($tenant) use ($loyaltyService) {
            // Check if tenant already has tiers
            if (\App\Models\MemberTier::where('tenant_id', $tenant->id)->count() === 0) {
                $loyaltyService->initializeDefaultTiers($tenant->id);
                $this->command->info("Initialized default member tiers for tenant: {$tenant->name}");
            }
        });
    }
}
