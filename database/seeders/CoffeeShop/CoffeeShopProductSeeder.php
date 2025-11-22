<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Store;

class CoffeeShopProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic products for coffee shop with modifier groups.
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        // Get categories
        $categories = Category::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('slug');

        // Get modifier groups
        $modifierGroups = ModifierGroup::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('name');

        $products = [
            // Espresso Category
            [
                'category_slug' => 'espresso',
                'name' => 'Espresso',
                'sku' => 'PROD-ESP-001',
                'description' => 'Single shot espresso murni',
                'price' => 15000,
                'cost_price' => 8000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 1,
                'modifier_groups' => ['Size', 'Extra Shot'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Double Espresso',
                'sku' => 'PROD-ESP-002',
                'description' => 'Double shot espresso',
                'price' => 20000,
                'cost_price' => 12000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 2,
                'modifier_groups' => ['Size'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Americano',
                'sku' => 'PROD-ESP-003',
                'description' => 'Espresso dengan air panas',
                'price' => 22000,
                'cost_price' => 10000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 3,
                'modifier_groups' => ['Size', 'Ice Level', 'Sweetness'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Cappuccino',
                'sku' => 'PROD-ESP-004',
                'description' => 'Espresso dengan steamed milk dan foam',
                'price' => 28000,
                'cost_price' => 14000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 4,
                'modifier_groups' => ['Size', 'Milk Type', 'Sweetness', 'Toppings'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Caffe Latte',
                'sku' => 'PROD-ESP-005',
                'description' => 'Espresso dengan steamed milk',
                'price' => 30000,
                'cost_price' => 15000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 5,
                'modifier_groups' => ['Size', 'Milk Type', 'Ice Level', 'Sweetness', 'Toppings'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Flat White',
                'sku' => 'PROD-ESP-006',
                'description' => 'Double espresso dengan microfoam milk',
                'price' => 32000,
                'cost_price' => 16000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 6,
                'modifier_groups' => ['Size', 'Milk Type', 'Sweetness'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Mocha',
                'sku' => 'PROD-ESP-007',
                'description' => 'Espresso dengan chocolate dan steamed milk',
                'price' => 33000,
                'cost_price' => 17000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 7,
                'modifier_groups' => ['Size', 'Milk Type', 'Ice Level', 'Sweetness', 'Toppings'],
            ],
            [
                'category_slug' => 'espresso',
                'name' => 'Caramel Macchiato',
                'sku' => 'PROD-ESP-008',
                'description' => 'Espresso dengan caramel syrup dan steamed milk',
                'price' => 35000,
                'cost_price' => 18000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 8,
                'modifier_groups' => ['Size', 'Milk Type', 'Ice Level', 'Sweetness', 'Toppings'],
            ],
            
            // Coffee Category
            [
                'category_slug' => 'coffee',
                'name' => 'Black Coffee',
                'sku' => 'PROD-COF-001',
                'description' => 'Kopi hitam tubruk',
                'price' => 12000,
                'cost_price' => 6000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 1,
                'modifier_groups' => ['Size', 'Ice Level', 'Sweetness'],
            ],
            [
                'category_slug' => 'coffee',
                'name' => 'V60 Pour Over',
                'sku' => 'PROD-COF-002',
                'description' => 'Manual brew pour over',
                'price' => 25000,
                'cost_price' => 12000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 2,
                'modifier_groups' => ['Size', 'Sweetness'],
            ],
            [
                'category_slug' => 'coffee',
                'name' => 'Cold Brew',
                'sku' => 'PROD-COF-003',
                'description' => 'Cold brewed coffee',
                'price' => 28000,
                'cost_price' => 13000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 3,
                'modifier_groups' => ['Size', 'Milk Type', 'Ice Level', 'Sweetness'],
            ],
            
            // Tea Category
            [
                'category_slug' => 'tea',
                'name' => 'Green Tea',
                'sku' => 'PROD-TEA-001',
                'description' => 'Teh hijau panas',
                'price' => 15000,
                'cost_price' => 7000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 1,
                'modifier_groups' => ['Size', 'Ice Level', 'Sweetness'],
            ],
            [
                'category_slug' => 'tea',
                'name' => 'Earl Grey',
                'sku' => 'PROD-TEA-002',
                'description' => 'Teh Earl Grey',
                'price' => 18000,
                'cost_price' => 8000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 2,
                'modifier_groups' => ['Size', 'Ice Level', 'Sweetness'],
            ],
            [
                'category_slug' => 'tea',
                'name' => 'Iced Tea',
                'sku' => 'PROD-TEA-003',
                'description' => 'Teh es manis',
                'price' => 12000,
                'cost_price' => 6000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 3,
                'modifier_groups' => ['Size', 'Ice Level', 'Sweetness'],
            ],
            
            // Non-Coffee Category
            [
                'category_slug' => 'non-coffee',
                'name' => 'Hot Chocolate',
                'sku' => 'PROD-NCF-001',
                'description' => 'Coklat panas',
                'price' => 25000,
                'cost_price' => 12000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 1,
                'modifier_groups' => ['Size', 'Milk Type', 'Toppings'],
            ],
            [
                'category_slug' => 'non-coffee',
                'name' => 'Matcha Latte',
                'sku' => 'PROD-NCF-002',
                'description' => 'Matcha dengan steamed milk',
                'price' => 32000,
                'cost_price' => 16000,
                'track_inventory' => false,
                'is_favorite' => true,
                'sort_order' => 2,
                'modifier_groups' => ['Size', 'Milk Type', 'Ice Level', 'Sweetness'],
            ],
            
            // Pastry Category
            [
                'category_slug' => 'pastry',
                'name' => 'Croissant',
                'sku' => 'PROD-PAS-001',
                'description' => 'Croissant buttery segar',
                'price' => 18000,
                'cost_price' => 9000,
                'track_inventory' => true,
                'is_favorite' => true,
                'sort_order' => 1,
                'modifier_groups' => [],
            ],
            [
                'category_slug' => 'pastry',
                'name' => 'Chocolate Muffin',
                'sku' => 'PROD-PAS-002',
                'description' => 'Muffin coklat',
                'price' => 20000,
                'cost_price' => 10000,
                'track_inventory' => true,
                'is_favorite' => true,
                'sort_order' => 2,
                'modifier_groups' => [],
            ],
            [
                'category_slug' => 'pastry',
                'name' => 'Blueberry Cheesecake',
                'sku' => 'PROD-PAS-003',
                'description' => 'Cheesecake blueberry',
                'price' => 35000,
                'cost_price' => 18000,
                'track_inventory' => true,
                'is_favorite' => false,
                'sort_order' => 3,
                'modifier_groups' => [],
            ],
            [
                'category_slug' => 'pastry',
                'name' => 'Cinnamon Roll',
                'sku' => 'PROD-PAS-004',
                'description' => 'Cinnamon roll hangat',
                'price' => 22000,
                'cost_price' => 11000,
                'track_inventory' => true,
                'is_favorite' => false,
                'sort_order' => 4,
                'modifier_groups' => [],
            ],
            
            // Snacks Category
            [
                'category_slug' => 'snacks',
                'name' => 'French Fries',
                'sku' => 'PROD-SNK-001',
                'description' => 'Kentang goreng',
                'price' => 15000,
                'cost_price' => 7000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 1,
                'modifier_groups' => [],
            ],
            [
                'category_slug' => 'snacks',
                'name' => 'Nachos',
                'sku' => 'PROD-SNK-002',
                'description' => 'Nachos dengan cheese sauce',
                'price' => 25000,
                'cost_price' => 12000,
                'track_inventory' => false,
                'is_favorite' => false,
                'sort_order' => 2,
                'modifier_groups' => [],
            ],
        ];

        foreach ($products as $productData) {
            $categorySlug = $productData['category_slug'];
            $modifierGroupNames = $productData['modifier_groups'];
            unset($productData['category_slug'], $productData['modifier_groups']);

            $category = $categories->get($categorySlug);
            if (!$category) {
                $this->command->warn("Category '{$categorySlug}' not found. Skipping product: {$productData['name']}");
                continue;
            }

            $productData['tenant_id'] = $tenantId;
            $productData['category_id'] = $category->id;

            $product = Product::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'sku' => $productData['sku'],
                ],
                $productData
            );

            // Attach modifier groups
            foreach ($modifierGroupNames as $sortOrder => $groupName) {
                $modifierGroup = $modifierGroups->get($groupName);
                if ($modifierGroup) {
                    $product->modifierGroups()->syncWithoutDetaching([
                        $modifierGroup->id => [
                            'is_required' => $modifierGroup->is_required,
                            'sort_order' => $sortOrder + 1,
                        ],
                    ]);
                }
            }
        }

        $this->command->info('✅ Coffee shop products created successfully!');
    }
}

