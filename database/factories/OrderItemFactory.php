<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->numberBetween(5000, 50000);

        return [
            'store_id' => Store::factory(),
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => $this->faker->words(2, true),
            'product_sku' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'product_options' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Configure the model factory for a specific store.
     */
    public function forStore(Store $store): static
    {
        return $this->state(function (array $attributes) use ($store) {
            return [
                'store_id' => $store->id,
            ];
        });
    }

    /**
     * Configure the model factory for a specific order.
     */
    public function forOrder(Order $order): static
    {
        return $this->state(function (array $attributes) use ($order) {
            return [
                'store_id' => $order->store_id,
                'order_id' => $order->id,
            ];
        });
    }

    /**
     * Configure the model factory for a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(function (array $attributes) use ($product) {
            return [
                'store_id' => $product->store_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'unit_price' => $product->price,
                'total_price' => $attributes['quantity'] * $product->price,
            ];
        });
    }

    /**
     * Configure the model factory with product options.
     */
    public function withOptions(array $options = []): static
    {
        return $this->state(function (array $attributes) use ($options) {
            $optionsPrice = collect($options)->sum('price_adjustment');
            $quantity = $attributes['quantity'];
            
            return [
                'product_options' => $options,
                'unit_price' => $attributes['unit_price'] + $optionsPrice,
                'total_price' => $quantity * ($attributes['unit_price'] + $optionsPrice),
            ];
        });
    }
}