<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StaffPerformance>
 */
class StaffPerformanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ordersProcessed = $this->faker->numberBetween(0, 50);
        $totalSales = $this->faker->randomFloat(2, 0, 5000);
        $hoursWorked = $this->faker->numberBetween(0, 12);
        
        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'orders_processed' => $ordersProcessed,
            'total_sales' => $totalSales,
            'average_order_value' => $ordersProcessed > 0 ? $totalSales / $ordersProcessed : 0,
            'refunds_processed' => $this->faker->numberBetween(0, 5),
            'refund_amount' => $this->faker->randomFloat(2, 0, 500),
            'hours_worked' => $hoursWorked,
            'sales_per_hour' => $hoursWorked > 0 ? $totalSales / $hoursWorked : 0,
            'customer_interactions' => $this->faker->numberBetween(0, 100),
            'customer_satisfaction_score' => $this->faker->randomFloat(2, 1, 5),
            'additional_metrics' => [
                'upsells' => $this->faker->numberBetween(0, 10),
                'complaints' => $this->faker->numberBetween(0, 3),
            ],
        ];
    }

    /**
     * Indicate high performance metrics.
     */
    public function highPerformance(): static
    {
        return $this->state(fn (array $attributes) => [
            'orders_processed' => $this->faker->numberBetween(30, 50),
            'total_sales' => $this->faker->randomFloat(2, 3000, 5000),
            'customer_satisfaction_score' => $this->faker->randomFloat(2, 4.5, 5.0),
        ]);
    }

    /**
     * Indicate low performance metrics.
     */
    public function lowPerformance(): static
    {
        return $this->state(fn (array $attributes) => [
            'orders_processed' => $this->faker->numberBetween(0, 10),
            'total_sales' => $this->faker->randomFloat(2, 0, 1000),
            'customer_satisfaction_score' => $this->faker->randomFloat(2, 1.0, 3.0),
        ]);
    }
}
