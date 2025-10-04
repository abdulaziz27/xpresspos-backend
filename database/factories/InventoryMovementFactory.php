<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement([
                InventoryMovement::TYPE_SALE,
                InventoryMovement::TYPE_PURCHASE,
                InventoryMovement::TYPE_ADJUSTMENT_IN,
                InventoryMovement::TYPE_ADJUSTMENT_OUT,
            ]),
            'quantity' => $this->faker->numberBetween(1, 50),
            'unit_cost' => $this->faker->randomFloat(2, 5, 100),
            'reason' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
