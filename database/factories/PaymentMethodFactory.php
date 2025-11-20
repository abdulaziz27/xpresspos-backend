<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gateway' => $this->faker->randomElement(['xendit', 'doku']), // NOTE: Midtrans removed
            'gateway_id' => $this->faker->unique()->regexify('[A-Za-z0-9]{20}'),
            'type' => $this->faker->randomElement(['card', 'bank_transfer', 'digital_wallet', 'va', 'qris']),
            'last_four' => $this->faker->numerify('####'),
            'expires_at' => $this->faker->optional(0.8)->dateTimeBetween('now', '+5 years'),
            'is_default' => false,
            'metadata' => [
                'payment_type' => $this->faker->randomElement(['credit_card', 'bank_transfer', 'gopay', 'shopeepay']),
                'bank' => $this->faker->randomElement(['bca', 'bni', 'bri', 'mandiri']),
            ],
        ];
    }

    /**
     * Indicate that the payment method is the default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the payment method is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-2 years', '-1 day'),
        ]);
    }

    /**
     * Indicate that the payment method is a card.
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'card',
            'metadata' => [
                'payment_type' => 'credit_card',
                'card_type' => $this->faker->randomElement(['credit', 'debit']),
                'bank' => $this->faker->randomElement(['bca', 'bni', 'bri', 'mandiri']),
            ],
        ]);
    }

    /**
     * Indicate that the payment method is a virtual account.
     */
    public function virtualAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'va',
            'expires_at' => null,
            'metadata' => [
                'payment_type' => 'bank_transfer',
                'bank' => $this->faker->randomElement(['bca', 'bni', 'bri', 'mandiri', 'permata']),
            ],
        ]);
    }

    /**
     * Indicate that the payment method is a digital wallet.
     */
    public function digitalWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'digital_wallet',
            'expires_at' => null,
            'metadata' => [
                'payment_type' => $this->faker->randomElement(['gopay', 'shopeepay', 'dana', 'ovo']),
            ],
        ]);
    }

    /**
     * Indicate that the payment method is QRIS.
     */
    public function qris(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'qris',
            'expires_at' => null,
            'metadata' => [
                'payment_type' => 'qris',
            ],
        ]);
    }
}