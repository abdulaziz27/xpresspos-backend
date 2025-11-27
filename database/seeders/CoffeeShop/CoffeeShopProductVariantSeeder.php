<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;

class CoffeeShopProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic product variants for coffee shop products.
     * Variants are grouped by type (Size, Milk Type, Ice Level, etc.)
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        // Get all products without global scopes
        $products = Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('sku');

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Make sure CoffeeShopProductSeeder runs first.');
            return;
        }

        // Define product variants by SKU
        // Format: 'sku' => [
        //     'variant_group' => [
        //         ['value' => 'value1', 'price_adjustment' => 0, 'sort_order' => 1],
        //         ['value' => 'value2', 'price_adjustment' => 2000, 'sort_order' => 2],
        //     ]
        // ]
        $productVariants = [
            // Espresso-based drinks with Size variants
            'PROD-ESP-001' => [ // Espresso
                'Size' => [
                    ['value' => 'Single Shot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Double Shot', 'price_adjustment' => 5000, 'sort_order' => 2],
                ],
            ],
            'PROD-ESP-002' => [ // Double Espresso
                'Size' => [
                    ['value' => 'Regular', 'price_adjustment' => 0, 'sort_order' => 1],
                ],
            ],
            'PROD-ESP-003' => [ // Americano
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
            'PROD-ESP-004' => [ // Cappuccino
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                    ['value' => 'Soy Milk', 'price_adjustment' => 2000, 'sort_order' => 4],
                ],
            ],
            'PROD-ESP-005' => [ // Caffe Latte
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                    ['value' => 'Soy Milk', 'price_adjustment' => 2000, 'sort_order' => 4],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
            'PROD-ESP-006' => [ // Flat White
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
            ],
            'PROD-ESP-007' => [ // Mocha
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
            'PROD-ESP-008' => [ // Caramel Macchiato
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],

            // Coffee Category
            'PROD-COF-001' => [ // Black Coffee
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -2000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
            'PROD-COF-002' => [ // V60 Pour Over
                'Size' => [
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 2],
                ],
            ],
            'PROD-COF-003' => [ // Cold Brew
                'Size' => [
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 2],
                ],
                'Milk Type' => [
                    ['value' => 'No Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Regular Milk', 'price_adjustment' => 2000, 'sort_order' => 2],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 4],
                ],
            ],

            // Tea Category
            'PROD-TEA-001' => [ // Green Tea
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -2000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
            'PROD-TEA-002' => [ // Earl Grey
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -2000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
            'PROD-TEA-003' => [ // Iced Tea
                'Size' => [
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 3000, 'sort_order' => 2],
                ],
            ],

            // Non-Coffee Category
            'PROD-NCF-001' => [ // Hot Chocolate
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
            ],
            'PROD-NCF-002' => [ // Matcha Latte
                'Size' => [
                    ['value' => 'Small (8oz)', 'price_adjustment' => -3000, 'sort_order' => 1],
                    ['value' => 'Regular (12oz)', 'price_adjustment' => 0, 'sort_order' => 2],
                    ['value' => 'Large (16oz)', 'price_adjustment' => 5000, 'sort_order' => 3],
                ],
                'Milk Type' => [
                    ['value' => 'Regular Milk', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Almond Milk', 'price_adjustment' => 3000, 'sort_order' => 2],
                    ['value' => 'Oat Milk', 'price_adjustment' => 3000, 'sort_order' => 3],
                ],
                'Ice Level' => [
                    ['value' => 'Hot', 'price_adjustment' => 0, 'sort_order' => 1],
                    ['value' => 'Iced', 'price_adjustment' => 2000, 'sort_order' => 2],
                ],
            ],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($productVariants as $sku => $variantGroups) {
            $product = $products->get($sku);
            
            if (!$product) {
                $this->command->warn("Product with SKU '{$sku}' not found. Skipping variants.");
                $skippedCount++;
                continue;
            }

            // Delete existing variants for this product to avoid duplicates
            ProductVariant::query()
                ->withoutGlobalScopes()
                ->where('product_id', $product->id)
                ->delete();

            // Create variants for each group
            foreach ($variantGroups as $groupName => $variants) {
                foreach ($variants as $variantData) {
                    ProductVariant::query()
                        ->withoutGlobalScopes()
                        ->create([
                            'tenant_id' => $tenantId,
                            'product_id' => $product->id,
                            'name' => $groupName,
                            'value' => $variantData['value'],
                            'price_adjustment' => $variantData['price_adjustment'],
                            'sort_order' => $variantData['sort_order'],
                            'is_active' => true,
                        ]);
                    $createdCount++;
                }
            }
        }

        $this->command->info("✅ Created {$createdCount} product variants successfully!");
        if ($skippedCount > 0) {
            $this->command->warn("⚠️ Skipped {$skippedCount} products (not found).");
        }
    }
}


