<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\ModifierGroup;
use App\Models\ModifierItem;
use App\Models\Store;

class CoffeeShopModifierGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic modifier groups and items for coffee shop.
     */
    public function run(): void
    {
        $store = Store::first();
        if (!$store) {
            $this->command->error('No store found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $store->tenant_id;

        $modifierGroups = [
            [
                'name' => 'Size',
                'description' => 'Ukuran gelas',
                'min_select' => 1,
                'max_select' => 1,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 1,
                'items' => [
                    ['name' => 'Regular (12oz)', 'price_delta' => 0, 'sort_order' => 1],
                    ['name' => 'Large (16oz)', 'price_delta' => 5000, 'sort_order' => 2],
                    ['name' => 'Extra Large (20oz)', 'price_delta' => 10000, 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Milk Type',
                'description' => 'Jenis susu',
                'min_select' => 0,
                'max_select' => 1,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 2,
                'items' => [
                    ['name' => 'Fresh Milk', 'price_delta' => 0, 'sort_order' => 1],
                    ['name' => 'Oat Milk', 'price_delta' => 3000, 'sort_order' => 2],
                    ['name' => 'Soy Milk', 'price_delta' => 2000, 'sort_order' => 3],
                    ['name' => 'Almond Milk', 'price_delta' => 4000, 'sort_order' => 4],
                    ['name' => 'No Milk', 'price_delta' => 0, 'sort_order' => 5],
                ],
            ],
            [
                'name' => 'Ice Level',
                'description' => 'Tingkat es',
                'min_select' => 0,
                'max_select' => 1,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 3,
                'items' => [
                    ['name' => 'Normal Ice', 'price_delta' => 0, 'sort_order' => 1],
                    ['name' => 'Less Ice', 'price_delta' => 0, 'sort_order' => 2],
                    ['name' => 'No Ice', 'price_delta' => 0, 'sort_order' => 3],
                    ['name' => 'Extra Ice', 'price_delta' => 0, 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Sweetness',
                'description' => 'Tingkat kemanisan',
                'min_select' => 0,
                'max_select' => 1,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 4,
                'items' => [
                    ['name' => 'Normal Sweet', 'price_delta' => 0, 'sort_order' => 1],
                    ['name' => 'Less Sweet', 'price_delta' => 0, 'sort_order' => 2],
                    ['name' => 'No Sugar', 'price_delta' => 0, 'sort_order' => 3],
                    ['name' => 'Extra Sweet', 'price_delta' => 0, 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Extra Shot',
                'description' => 'Tambahan espresso shot',
                'min_select' => 0,
                'max_select' => 3,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 5,
                'items' => [
                    ['name' => '1 Extra Shot', 'price_delta' => 5000, 'sort_order' => 1],
                    ['name' => '2 Extra Shots', 'price_delta' => 10000, 'sort_order' => 2],
                    ['name' => '3 Extra Shots', 'price_delta' => 15000, 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Syrup Flavor',
                'description' => 'Rasa syrup',
                'min_select' => 0,
                'max_select' => 2,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 6,
                'items' => [
                    ['name' => 'Vanilla', 'price_delta' => 3000, 'sort_order' => 1],
                    ['name' => 'Caramel', 'price_delta' => 3000, 'sort_order' => 2],
                    ['name' => 'Hazelnut', 'price_delta' => 3500, 'sort_order' => 3],
                    ['name' => 'Chocolate', 'price_delta' => 3000, 'sort_order' => 4],
                ],
            ],
            [
                'name' => 'Toppings',
                'description' => 'Topping tambahan',
                'min_select' => 0,
                'max_select' => 3,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 7,
                'items' => [
                    ['name' => 'Whipped Cream', 'price_delta' => 5000, 'sort_order' => 1],
                    ['name' => 'Chocolate Chips', 'price_delta' => 3000, 'sort_order' => 2],
                    ['name' => 'Caramel Drizzle', 'price_delta' => 3000, 'sort_order' => 3],
                    ['name' => 'Cinnamon Powder', 'price_delta' => 2000, 'sort_order' => 4],
                ],
            ],
        ];

        foreach ($modifierGroups as $groupData) {
            $items = $groupData['items'];
            unset($groupData['items']);

            $group = ModifierGroup::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $groupData['name'],
                ],
                array_merge($groupData, [
                    'tenant_id' => $tenantId,
                ])
            );

            // Create modifier items
            foreach ($items as $itemData) {
                ModifierItem::query()->withoutGlobalScopes()->firstOrCreate(
                    [
                        'modifier_group_id' => $group->id,
                        'name' => $itemData['name'],
                    ],
                    array_merge($itemData, [
                        'tenant_id' => $tenantId,
                    ])
                );
            }
        }

        $this->command->info('✅ Coffee shop modifier groups and items created successfully!');
    }
}

