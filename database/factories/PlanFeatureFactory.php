<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'feature_code' => $this->faker->randomElement([
                'MAX_STORES',
                'MAX_PRODUCTS',
                'MAX_STAFF',
                'MAX_TRANSACTIONS_PER_YEAR',
                'ALLOW_LOYALTY',
                'ALLOW_MULTI_STORE',
                'ALLOW_API_ACCESS',
            ]),
            'limit_value' => $this->faker->randomElement(['1', '3', '5', '10', '100', '1000', '10000', '-1']),
            'is_enabled' => true,
        ];
    }
}

