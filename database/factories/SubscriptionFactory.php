<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-1 year', 'now');
        $billingCycle = $this->faker->randomElement(['monthly', 'annual']);
        $endsAt = $billingCycle === 'monthly' 
            ? (clone $startsAt)->modify('+1 month')
            : (clone $startsAt)->modify('+1 year');

        return [
            'tenant_id' => \App\Models\Tenant::factory(), // Subscription per tenant, bukan per store
            'plan_id' => \App\Models\Plan::factory(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'cancelled', 'expired']),
            'billing_cycle' => $billingCycle,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween($startsAt, $endsAt),
            'amount' => $this->faker->randomFloat(2, 99000, 399000),
            'metadata' => [
                'payment_method' => $this->faker->randomElement(['credit_card', 'bank_transfer']),
                'auto_renew' => $this->faker->boolean(),
            ],
        ];
    }
}
