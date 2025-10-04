<?php

namespace Database\Factories;

use App\Models\CashSession;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashSession>
 */
class CashSessionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CashSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openedAt = $this->faker->dateTimeBetween('-1 week', 'now');
        $closedAt = $this->faker->boolean(70) ? $this->faker->dateTimeBetween($openedAt, 'now') : null;
        $status = $closedAt ? 'closed' : 'open';
        
        $openingBalance = $this->faker->randomFloat(2, 50, 500);
        $cashSales = $status === 'closed' ? $this->faker->randomFloat(2, 0, 1000) : 0;
        $cashExpenses = $status === 'closed' ? $this->faker->randomFloat(2, 0, 200) : 0;
        $expectedBalance = $openingBalance + $cashSales - $cashExpenses;
        $variance = $status === 'closed' ? $this->faker->randomFloat(2, -20, 20) : 0;
        $closingBalance = $status === 'closed' ? $expectedBalance + $variance : null;

        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'expected_balance' => $status === 'closed' ? $expectedBalance : null,
            'cash_sales' => $cashSales,
            'cash_expenses' => $cashExpenses,
            'variance' => $variance,
            'status' => $status,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the cash session is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'closing_balance' => null,
            'expected_balance' => null,
            'cash_sales' => 0,
            'cash_expenses' => 0,
            'variance' => 0,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the cash session is closed.
     */
    public function closed(): static
    {
        return $this->state(function (array $attributes) {
            $openingBalance = $attributes['opening_balance'];
            $cashSales = $this->faker->randomFloat(2, 0, 1000);
            $cashExpenses = $this->faker->randomFloat(2, 0, 200);
            $expectedBalance = $openingBalance + $cashSales - $cashExpenses;
            $variance = $this->faker->randomFloat(2, -20, 20);
            $closingBalance = $expectedBalance + $variance;

            return [
                'status' => 'closed',
                'closing_balance' => $closingBalance,
                'expected_balance' => $expectedBalance,
                'cash_sales' => $cashSales,
                'cash_expenses' => $cashExpenses,
                'variance' => $variance,
                'closed_at' => $this->faker->dateTimeBetween($attributes['opened_at'], 'now'),
            ];
        });
    }

    /**
     * Indicate that the cash session has a variance.
     */
    public function withVariance(float $variance = null): static
    {
        return $this->state(function (array $attributes) use ($variance) {
            $varianceAmount = $variance ?? $this->faker->randomFloat(2, -50, 50);
            $expectedBalance = $attributes['expected_balance'] ?? 
                ($attributes['opening_balance'] + $attributes['cash_sales'] - $attributes['cash_expenses']);
            
            return [
                'variance' => $varianceAmount,
                'closing_balance' => $expectedBalance + $varianceAmount,
            ];
        });
    }
}