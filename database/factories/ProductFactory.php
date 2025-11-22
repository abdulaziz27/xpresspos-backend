<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'description' => $this->faker->text,
            'price' => $this->faker->randomFloat(2, 1, 100),
            'cost_price' => $this->faker->randomFloat(2, 0.5, 50),
            'image' => $this->faker->imageUrl(),
            'track_inventory' => $this->faker->boolean(70),
            'status' => $this->faker->boolean(90),
            'is_favorite' => $this->faker->boolean(20),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'category_id' => \App\Models\Category::factory(),
        ];
    }
}
