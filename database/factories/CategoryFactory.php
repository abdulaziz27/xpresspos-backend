<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
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
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug,
            'description' => $this->faker->text,
            'image' => $this->faker->imageUrl(),
            'status' => $this->faker->boolean(90),
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
