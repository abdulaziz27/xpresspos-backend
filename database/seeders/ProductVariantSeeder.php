<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $storeId = config('demo.store_id') ?? \App\Models\Store::first()->id;
        
        // Get products by SKU
        $products = Product::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->get()
            ->keyBy('sku');

        // Espresso variants
        if ($products->has('ESP001')) {
            $this->createVariants($products['ESP001'], [
                ['name' => 'Size', 'value' => 'Single Shot', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Double Shot', 'price_adjustment' => 8000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
            ]);
        }

        // Cappuccino variants
        if ($products->has('CAP001')) {
            $this->createVariants($products['CAP001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 8000],
                ['name' => 'Milk', 'value' => 'Regular Milk', 'price_adjustment' => 0],
                ['name' => 'Milk', 'value' => 'Oat Milk', 'price_adjustment' => 8000],
                ['name' => 'Milk', 'value' => 'Almond Milk', 'price_adjustment' => 6000],
                ['name' => 'Sugar', 'value' => 'No Sugar', 'price_adjustment' => 0],
                ['name' => 'Sugar', 'value' => 'Regular Sugar', 'price_adjustment' => 0],
                ['name' => 'Sugar', 'value' => 'Extra Sweet', 'price_adjustment' => 2000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
                ['name' => 'Temperature', 'value' => 'Iced', 'price_adjustment' => 3000],
            ]);
        }

        // Latte variants
        if ($products->has('LAT001')) {
            $this->createVariants($products['LAT001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 8000],
                ['name' => 'Milk', 'value' => 'Regular Milk', 'price_adjustment' => 0],
                ['name' => 'Milk', 'value' => 'Oat Milk', 'price_adjustment' => 8000],
                ['name' => 'Milk', 'value' => 'Soy Milk', 'price_adjustment' => 5000],
                ['name' => 'Flavor', 'value' => 'Original', 'price_adjustment' => 0],
                ['name' => 'Flavor', 'value' => 'Vanilla', 'price_adjustment' => 5000],
                ['name' => 'Flavor', 'value' => 'Caramel', 'price_adjustment' => 5000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
                ['name' => 'Temperature', 'value' => 'Iced', 'price_adjustment' => 3000],
            ]);
        }

        // Green Tea variants
        if ($products->has('GT001')) {
            $this->createVariants($products['GT001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 5000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
                ['name' => 'Temperature', 'value' => 'Iced', 'price_adjustment' => 2000],
            ]);
        }

        // Earl Grey variants
        if ($products->has('EG001')) {
            $this->createVariants($products['EG001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 5000],
                ['name' => 'Milk', 'value' => 'No Milk', 'price_adjustment' => 0],
                ['name' => 'Milk', 'value' => 'With Milk', 'price_adjustment' => 3000],
                ['name' => 'Temperature', 'value' => 'Hot', 'price_adjustment' => 0],
            ]);
        }

        // Croissant variants
        if ($products->has('CRO001')) {
            $this->createVariants($products['CRO001'], [
                ['name' => 'Type', 'value' => 'Plain', 'price_adjustment' => 0],
                ['name' => 'Type', 'value' => 'Chocolate', 'price_adjustment' => 5000],
                ['name' => 'Type', 'value' => 'Almond', 'price_adjustment' => 8000],
            ]);
        }

        // Muffin variants
        if ($products->has('MUF001')) {
            $this->createVariants($products['MUF001'], [
                ['name' => 'Size', 'value' => 'Regular', 'price_adjustment' => 0],
                ['name' => 'Size', 'value' => 'Large', 'price_adjustment' => 5000],
                ['name' => 'Topping', 'value' => 'No Topping', 'price_adjustment' => 0],
                ['name' => 'Topping', 'value' => 'Extra Chocolate', 'price_adjustment' => 3000],
            ]);
        }

        $this->command->info('Created variants for ' . $products->count() . ' products');
    }

    private function createVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantData) {
            ProductVariant::withoutGlobalScopes()->updateOrCreate(
                [
                    'store_id' => $product->store_id,
                    'product_id' => $product->id,
                    'name' => $variantData['name'],
                    'value' => $variantData['value'],
                ],
                [
                    'price_adjustment' => $variantData['price_adjustment'],
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
        }
    }
}