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
            // Authenticated checkout fields (NEW)
            'user_id' => null, // Akan di-set di test jika perlu
            'tenant_id' => null, // Akan di-set di test jika perlu
            'plan_id' => null, // Akan di-set di test
            'billing_cycle' => $this->faker->randomElement(['monthly', 'annual']),
            
            // Status & stage
            'status' => 'pending',
            'stage' => 'new',
            'payment_status' => 'pending',
            'payment_amount' => null,
            'paid_at' => null,
            
            // Legacy fields (untuk backward compatibility)
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'company' => $this->faker->company(),
            'business_name' => $this->faker->company(),
            'business_type' => $this->faker->randomElement(['retail', 'restaurant', 'cafe', 'fashion']),
            'plan' => $this->faker->randomElement(['starter', 'growth', 'scale']),
            'phone' => $this->faker->phoneNumber(),
            'country' => $this->faker->country(),
            'preferred_contact_method' => $this->faker->randomElement(['email', 'phone', 'whatsapp']),
            'notes' => $this->faker->sentence(),
            
            // Metadata
            'meta' => [
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'source' => $this->faker->randomElement(['landing_page', 'dashboard']),
            ],
            'follow_up_logs' => [
                ['timestamp' => now()->toISOString(), 'message' => 'Initial capture'],
            ],
            
            // Links (nullable, diisi setelah provisioning)
            'subscription_id' => null,
            'provisioned_store_id' => null,
            'provisioned_user_id' => null,
            'provisioned_at' => null,
            'xendit_invoice_id' => null,
            'onboarding_url' => null,
        ];
    }

    /**
     * Set authenticated checkout (user_id & tenant_id sudah ada).
     */
    public function authenticated(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => \App\Models\User::factory(),
            'tenant_id' => \App\Models\Tenant::factory(),
            'stage' => 'payment_pending',
        ]);
    }

    /**
     * Set anonymous checkout (legacy flow).
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'tenant_id' => null,
            'stage' => 'new',
        ]);
    }
}
