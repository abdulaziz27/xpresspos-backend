<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 10, 1000);
        $taxAmount = $amount * 0.1; // 10% tax
        
        return [
            'subscription_id' => Subscription::factory(),
            'invoice_number' => null, // Will be generated automatically
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $amount + $taxAmount,
            'status' => $this->faker->randomElement(['pending', 'paid', 'failed']),
            'due_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'paid_at' => null,
            'line_items' => [
                [
                    'description' => 'Subscription Plan',
                    'amount' => $amount,
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'billing_reason' => $this->faker->randomElement(['subscription_create', 'subscription_cycle', 'subscription_update']),
                'payment_intent_id' => $this->faker->optional()->regexify('[A-Za-z0-9]{20}'),
            ],
        ];
    }

    /**
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the invoice is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the invoice is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the invoice is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }

    /**
     * Indicate that the invoice is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Indicate that the invoice is due soon.
     */
    public function dueSoon(int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'due_date' => $this->faker->dateTimeBetween('now', "+{$days} days"),
            'paid_at' => null,
        ]);
    }

    /**
     * Create an invoice with specific amount.
     */
    public function withAmount(float $amount): static
    {
        $taxAmount = $amount * 0.1;
        
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $amount + $taxAmount,
            'line_items' => [
                [
                    'description' => 'Subscription Plan',
                    'amount' => $amount,
                    'quantity' => 1,
                ],
            ],
        ]);
    }
}