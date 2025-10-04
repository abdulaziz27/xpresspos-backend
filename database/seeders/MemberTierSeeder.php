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
        
        // Initialize default tiers for all existing stores
        \App\Models\Store::all()->each(function ($store) use ($loyaltyService) {
            // Check if store already has tiers
            if (\App\Models\MemberTier::where('store_id', $store->id)->count() === 0) {
                $loyaltyService->initializeDefaultTiers($store->id);
                $this->command->info("Initialized default member tiers for store: {$store->name}");
            }
        });
    }
}
