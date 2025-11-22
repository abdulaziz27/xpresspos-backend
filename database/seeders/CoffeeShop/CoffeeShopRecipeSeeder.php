<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\InventoryItem;
use App\Models\Store;

class CoffeeShopRecipeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic recipes for key coffee shop products.
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        // Get inventory items
        $inventoryItems = InventoryItem::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('sku');

        // Get products
        $products = Product::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('sku');

        $recipes = [
            // Caffe Latte Recipe
            [
                'product_sku' => 'PROD-ESP-005',
                'name' => 'Caffe Latte Recipe',
                'description' => 'Resep standar untuk Caffe Latte',
                'yield_quantity' => 1,
                'yield_unit' => 'piece',
                'is_active' => true,
                'items' => [
                    [
                        'inventory_sku' => 'INV-COFFEE-ESPRESSO-GROUND',
                        'quantity' => 0.018, // 18g espresso grounds
                        'notes' => 'Double shot espresso',
                    ],
                    [
                        'inventory_sku' => 'INV-DAIRY-MILK-FRESH',
                        'quantity' => 0.200, // 200ml fresh milk
                        'notes' => 'Steamed milk',
                    ],
                    [
                        'inventory_sku' => 'INV-SWEET-SUGAR-WHITE',
                        'quantity' => 0.010, // 10g sugar (optional, untuk default)
                        'notes' => 'Default sugar',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-16OZ',
                        'quantity' => 1,
                        'notes' => 'Cup 16oz',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-LID',
                        'quantity' => 1,
                        'notes' => 'Lid',
                    ],
                ],
            ],
            
            // Cappuccino Recipe
            [
                'product_sku' => 'PROD-ESP-004',
                'name' => 'Cappuccino Recipe',
                'description' => 'Resep standar untuk Cappuccino',
                'yield_quantity' => 1,
                'yield_unit' => 'piece',
                'is_active' => true,
                'items' => [
                    [
                        'inventory_sku' => 'INV-COFFEE-ESPRESSO-GROUND',
                        'quantity' => 0.018,
                        'notes' => 'Double shot espresso',
                    ],
                    [
                        'inventory_sku' => 'INV-DAIRY-MILK-FRESH',
                        'quantity' => 0.150, // 150ml milk (less than latte)
                        'notes' => 'Steamed milk dengan foam',
                    ],
                    [
                        'inventory_sku' => 'INV-DAIRY-CREAM-WHIPPED',
                        'quantity' => 0.020, // 20ml whipped cream
                        'notes' => 'Whipped cream untuk foam',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-16OZ',
                        'quantity' => 1,
                        'notes' => 'Cup 16oz',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-LID',
                        'quantity' => 1,
                        'notes' => 'Lid',
                    ],
                ],
            ],
            
            // Cold Brew Recipe
            [
                'product_sku' => 'PROD-COF-003',
                'name' => 'Cold Brew Recipe',
                'description' => 'Resep standar untuk Cold Brew',
                'yield_quantity' => 1,
                'yield_unit' => 'piece',
                'is_active' => true,
                'items' => [
                    [
                        'inventory_sku' => 'INV-COFFEE-ARABICA',
                        'quantity' => 0.030, // 30g coffee beans untuk cold brew
                        'notes' => 'Arabica beans untuk cold brew',
                    ],
                    [
                        'inventory_sku' => 'INV-INGREDIENT-ICE',
                        'quantity' => 0.200, // 200g ice
                        'notes' => 'Ice cubes',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-16OZ',
                        'quantity' => 1,
                        'notes' => 'Cup 16oz',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-LID',
                        'quantity' => 1,
                        'notes' => 'Lid',
                    ],
                ],
            ],
            
            // Matcha Latte Recipe
            [
                'product_sku' => 'PROD-NCF-002',
                'name' => 'Matcha Latte Recipe',
                'description' => 'Resep standar untuk Matcha Latte',
                'yield_quantity' => 1,
                'yield_unit' => 'piece',
                'is_active' => true,
                'items' => [
                    [
                        'inventory_sku' => 'INV-INGREDIENT-MATCHA',
                        'quantity' => 0.008, // 8g matcha powder
                        'notes' => 'Matcha powder',
                    ],
                    [
                        'inventory_sku' => 'INV-DAIRY-MILK-FRESH',
                        'quantity' => 0.250, // 250ml milk
                        'notes' => 'Steamed milk',
                    ],
                    [
                        'inventory_sku' => 'INV-SWEET-SUGAR-WHITE',
                        'quantity' => 0.015, // 15g sugar
                        'notes' => 'Sugar untuk matcha',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-16OZ',
                        'quantity' => 1,
                        'notes' => 'Cup 16oz',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-LID',
                        'quantity' => 1,
                        'notes' => 'Lid',
                    ],
                ],
            ],
            
            // Green Tea Recipe
            [
                'product_sku' => 'PROD-TEA-001',
                'name' => 'Green Tea Recipe',
                'description' => 'Resep standar untuk Green Tea',
                'yield_quantity' => 1,
                'yield_unit' => 'piece',
                'is_active' => true,
                'items' => [
                    [
                        'inventory_sku' => 'INV-TEA-GREEN',
                        'quantity' => 0.005, // 5g green tea leaves
                        'notes' => 'Green tea leaves',
                    ],
                    [
                        'inventory_sku' => 'INV-SWEET-SUGAR-WHITE',
                        'quantity' => 0.010, // 10g sugar
                        'notes' => 'Sugar',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-16OZ',
                        'quantity' => 1,
                        'notes' => 'Cup 16oz',
                    ],
                    [
                        'inventory_sku' => 'INV-PACKAGING-CUP-LID',
                        'quantity' => 1,
                        'notes' => 'Lid',
                    ],
                ],
            ],
        ];

        foreach ($recipes as $recipeData) {
            $productSku = $recipeData['product_sku'];
            $items = $recipeData['items'];
            unset($recipeData['product_sku'], $recipeData['items']);

            $product = $products->get($productSku);
            if (!$product) {
                $this->command->warn("Product '{$productSku}' not found. Skipping recipe.");
                continue;
            }

            $recipe = Recipe::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'name' => $recipeData['name'],
                ],
                array_merge($recipeData, [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                ])
            );

            // Create recipe items
            foreach ($items as $itemData) {
                $inventorySku = $itemData['inventory_sku'];
                $quantity = $itemData['quantity'];
                $notes = $itemData['notes'] ?? null;

                $inventoryItem = $inventoryItems->get($inventorySku);
                if (!$inventoryItem) {
                    $this->command->warn("Inventory item '{$inventorySku}' not found. Skipping recipe item.");
                    continue;
                }

                RecipeItem::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'recipe_id' => $recipe->id,
                        'inventory_item_id' => $inventoryItem->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'recipe_id' => $recipe->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity' => $quantity,
                        'uom_id' => $inventoryItem->uom_id,
                        'unit_cost' => $inventoryItem->default_cost ?? 0,
                        'notes' => $notes,
                    ]
                );
            }

            // Recalculate recipe costs
            $recipe->refresh(); // Refresh to ensure product relationship is loaded
            $recipe->recalculateCosts();
            
            // Update product cost_price (refresh product to get latest recipe data)
            $product->refresh();
            $product->recalculateCostPriceFromRecipe();
        }

        $this->command->info('✅ Coffee shop recipes created successfully!');
    }
}

