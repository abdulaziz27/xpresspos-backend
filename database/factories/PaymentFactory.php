<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => \App\Models\Store::factory(),
            'order_id' => \App\Models\Order::factory(),
            'payment_method' => $this->faker->randomElement(['cash', 'credit_card', 'debit_card', 'qris', 'bank_transfer', 'e_wallet']),
            'amount' => $this->faker->randomFloat(2, 10000, 500000),
            'reference_number' => $this->faker->optional()->regexify('[A-Z0-9]{10}'),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
            'processed_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
