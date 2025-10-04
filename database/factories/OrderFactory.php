<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-' . $this->faker->unique()->numberBetween(100000, 999999),
            'status' => $this->faker->randomElement(['draft', 'open', 'completed', 'cancelled']),
            'subtotal' => $this->faker->randomFloat(2, 10, 500),
            'tax_amount' => $this->faker->randomFloat(2, 1, 50),
            'discount_amount' => $this->faker->randomFloat(2, 0, 20),
            'total_amount' => function (array $attributes) {
                return $attributes['subtotal'] + $attributes['tax_amount'] - $attributes['discount_amount'];
            },
            'notes' => $this->faker->optional()->sentence(),
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the order is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }
}