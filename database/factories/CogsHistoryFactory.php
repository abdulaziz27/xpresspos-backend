<?php

namespace Database\Factories;

use App\Models\CogsHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CogsHistory>
 */
class CogsHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantitySold = $this->faker->numberBetween(1, 20);
        $unitCost = $this->faker->randomFloat(2, 5, 50);
        
        return [
            'quantity_sold' => $quantitySold,
            'unit_cost' => $unitCost,
            'total_cogs' => $quantitySold * $unitCost,
            'calculation_method' => $this->faker->randomElement([
                CogsHistory::METHOD_WEIGHTED_AVERAGE,
                CogsHistory::METHOD_FIFO,
                CogsHistory::METHOD_LIFO,
            ]),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
