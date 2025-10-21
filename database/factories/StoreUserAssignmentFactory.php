<?php

namespace Database\Factories;

use App\Enums\AssignmentRoleEnum;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoreUserAssignment>
 */
class StoreUserAssignmentFactory extends Factory
{
    protected $model = StoreUserAssignment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'assignment_role' => $this->faker->randomElement(AssignmentRoleEnum::cases()),
            'is_primary' => $this->faker->boolean(30), // 30% chance of being primary
        ];
    }

    /**
     * Create a primary assignment.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Create a secondary assignment.
     */
    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
        ]);
    }

    /**
     * Create an owner assignment.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_role' => AssignmentRoleEnum::OWNER,
            'is_primary' => true,
        ]);
    }

    /**
     * Create an admin assignment.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_role' => AssignmentRoleEnum::ADMIN,
        ]);
    }

    /**
     * Create a manager assignment.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_role' => AssignmentRoleEnum::MANAGER,
        ]);
    }

    /**
     * Create a staff assignment.
     */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_role' => AssignmentRoleEnum::STAFF,
        ]);
    }

    /**
     * Create assignment for specific user and store.
     */
    public function forUserAndStore(User $user, Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);
    }
}