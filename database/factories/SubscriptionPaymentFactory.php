<?php

namespace Database\Factories;

use App\Models\LandingSubscription;
use App\Models\Subscription;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'paid', 'expired', 'failed']);
        $paidAt = $status === 'paid' ? now() : null;

        return [
            'landing_subscription_id' => LandingSubscription::factory(),
            'subscription_id' => null,
            'invoice_id' => null,
            'xendit_invoice_id' => 'xendit_inv_' . $this->faker->unique()->uuid(),
            'external_id' => 'ext_' . $this->faker->unique()->uuid(),
            'payment_method' => $this->faker->randomElement(['bank_transfer', 'e_wallet', 'qris', 'credit_card']),
            'payment_channel' => $this->faker->randomElement(['BCA', 'OVO', 'DANA', 'SHOPEEPAY', null]),
            'amount' => $this->faker->randomFloat(2, 99000, 399000),
            'gateway_fee' => $this->faker->randomFloat(2, 0, 5000),
            'status' => $status,
            'gateway_response' => [
                'status' => strtoupper($status),
                'paid_at' => $paidAt?->toISOString(),
            ],
            'paid_at' => $paidAt,
            'expires_at' => now()->addDays(1),
        ];
    }

    /**
     * Indicate that the payment is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
            'gateway_response' => array_merge($attributes['gateway_response'] ?? [], [
                'status' => 'PAID',
                'paid_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
            'expires_at' => now()->addDays(1),
        ]);
    }

    /**
     * Indicate that the payment has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'paid_at' => null,
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the payment has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }
}

