<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use App\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'office_supplies',
            'utilities',
            'maintenance',
            'marketing',
            'travel',
            'meals',
            'professional_services',
            'inventory',
            'equipment',
            'rent',
            'insurance',
            'taxes',
            'miscellaneous'
        ];

        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'cash_session_id' => null, // Optional by default
            'category' => $this->faker->randomElement($categories),
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, 5, 500),
            'receipt_number' => $this->faker->optional()->bothify('RCP-####'),
            'vendor' => $this->faker->optional()->company(),
            'expense_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the expense is linked to a cash session.
     */
    public function withCashSession(): static
    {
        return $this->state(fn (array $attributes) => [
            'cash_session_id' => CashSession::factory(),
        ]);
    }

    /**
     * Indicate that the expense is for office supplies.
     */
    public function officeSupplies(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'office_supplies',
            'description' => 'Office supplies purchase',
            'vendor' => 'Office Depot',
        ]);
    }

    /**
     * Indicate that the expense is for utilities.
     */
    public function utilities(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'utilities',
            'description' => 'Monthly utility bill',
            'vendor' => 'Electric Company',
        ]);
    }

    /**
     * Indicate that the expense is for maintenance.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'maintenance',
            'description' => 'Equipment maintenance and repair',
            'vendor' => 'Maintenance Services Inc',
        ]);
    }

    /**
     * Set a specific amount for the expense.
     */
    public function amount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    /**
     * Set a specific category for the expense.
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Set a specific date for the expense.
     */
    public function date(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_date' => $date,
        ]);
    }
}