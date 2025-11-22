<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;

class CoffeeShopProductPriceHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic product price history for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Check if price histories already exist
        $existingHistories = ProductPriceHistory::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        if ($existingHistories > 0) {
            $this->command->info("⏭️  Tenant already has {$existingHistories} price histor(ies). Skipping...");
            return;
        }

        $this->command->info("💰 Creating product price histories for tenant: {$tenant->name}");

        // Get products
        $products = Product::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', true)
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Skipping price histories.');
            return;
        }

        $owner = User::where('email', 'owner@xpresspos.id')->first();
        $reasons = ['Harga pasar naik', 'Kenaikan biaya bahan baku', 'Promosi harga', 'Penyesuaian margin'];

        // Create price history for some products
        foreach ($products->random(min(5, $products->count())) as $product) {
            // Create 1-2 price changes per product
            $changeCount = rand(1, 2);
            
            $currentPrice = $product->price;
            $currentCostPrice = $product->cost_price ?? 0;
            
            for ($i = 0; $i < $changeCount; $i++) {
                // Calculate old prices (before change)
                $priceChange = rand(-5000, 5000); // ±Rp 5.000
                $oldPrice = max(1000, $currentPrice - $priceChange);
                
                $costPriceChange = rand(-2000, 2000); // ±Rp 2.000
                $oldCostPrice = max(500, $currentCostPrice - $costPriceChange);
                
                // Random date (past 60 days)
                $effectiveDate = now()->subDays(rand(1, 60));
                
                ProductPriceHistory::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'old_price' => $oldPrice,
                    'new_price' => $currentPrice,
                    'old_cost_price' => $oldCostPrice,
                    'new_cost_price' => $currentCostPrice,
                    'changed_by' => $owner->id,
                    'reason' => $reasons[array_rand($reasons)],
                    'effective_date' => $effectiveDate,
                ]);

                // Update current prices for next iteration
                $currentPrice = $oldPrice;
                $currentCostPrice = $oldCostPrice;
            }

            $this->command->line("   ✓ Created price history for: {$product->name}");
        }

        $this->command->info("✅ Successfully created product price histories for tenant: {$tenant->name}");
    }
}

