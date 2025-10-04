<?php

namespace Database\Factories;

use App\Models\LandingSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LandingSubscription>
 */
class LandingSubscriptionFactory extends Factory
{
    protected $model = LandingSubscription::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'company' => $this->faker->company(),
            'plan' => $this->faker->randomElement(['starter', 'growth', 'scale']),
            'status' => 'pending',
            'stage' => 'new',
            'phone' => $this->faker->phoneNumber(),
            'country' => $this->faker->country(),
            'preferred_contact_method' => $this->faker->randomElement(['email', 'phone', 'whatsapp']),
            'notes' => $this->faker->sentence(),
            'meta' => [
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ],
            'follow_up_logs' => [
                ['timestamp' => now()->toISOString(), 'message' => 'Initial capture'],
            ],
        ];
    }
}
