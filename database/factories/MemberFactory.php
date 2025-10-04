<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_number' => 'MBR' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->name(),
            'email' => $this->faker->optional()->email(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->optional()->date(),
            'address' => $this->faker->optional()->address(),
            'loyalty_points' => $this->faker->numberBetween(0, 5000),
            'total_spent' => $this->faker->randomFloat(2, 0, 10000000),
            'visit_count' => $this->faker->numberBetween(1, 100),
            'last_visit_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_active' => $this->faker->boolean(90),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
