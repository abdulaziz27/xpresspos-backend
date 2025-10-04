<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionUsage>
 */
class SubscriptionUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'feature_type' => $this->faker->randomElement(['products', 'users', 'transactions']),
            'current_usage' => $this->faker->numberBetween(0, 1000),
            'annual_quota' => $this->faker->numberBetween(1000, 10000),
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
            'soft_cap_triggered' => false,
        ];
    }
}
