<?php

namespace Database\Factories;

use App\Models\Discount;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Discount>
 */
class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([Discount::TYPE_PERCENTAGE, Discount::TYPE_FIXED]);

        $value = $type === Discount::TYPE_PERCENTAGE
            ? $this->faker->randomFloat(2, 1, 50)
            : $this->faker->randomFloat(2, 5000, 50000);

        return [
            'store_id' => Store::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'type' => $type,
            'value' => $value,
            'status' => Discount::STATUS_ACTIVE,
            'expired_date' => $this->faker->optional()->dateTimeBetween('now', '+1 year')?->format('Y-m-d'),
        ];
    }
}
