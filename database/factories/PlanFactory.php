<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'price' => 99000,
                'annual_price' => 990000,
                'features' => ['pos', 'basic_reports', 'customer_management'],
                'limits' => ['products' => 100, 'users' => 2, 'transactions' => 1000],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'price' => 199000,
                'annual_price' => 1990000,
                'features' => ['pos', 'inventory_tracking', 'advanced_reports', 'cogs_calculation'],
                'limits' => ['products' => 500, 'users' => 5, 'transactions' => 5000],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 399000,
                'annual_price' => 3990000,
                'features' => ['pos', 'inventory_tracking', 'advanced_reports', 'multi_outlet', 'api_access'],
                'limits' => ['products' => -1, 'users' => -1, 'transactions' => -1],
            ],
        ];

        $plan = $this->faker->randomElement($plans);

        return [
            'name' => $plan['name'],
            'slug' => $plan['slug'] . '-' . $this->faker->unique()->randomNumber(5),
            'description' => $this->faker->sentence(),
            'price' => $plan['price'],
            'annual_price' => $plan['annual_price'],
            'features' => $plan['features'],
            'limits' => $plan['limits'],
            'is_active' => true,
            'sort_order' => array_search($plan, $plans),
        ];
    }
}
