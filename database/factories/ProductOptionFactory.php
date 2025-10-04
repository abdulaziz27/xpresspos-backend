<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOption>
 */
class ProductOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $options = [
            ['name' => 'Size', 'values' => ['Small', 'Medium', 'Large'], 'adjustments' => [0, 5000, 10000]],
            ['name' => 'Temperature', 'values' => ['Hot', 'Cold', 'Ice'], 'adjustments' => [0, 0, 2000]],
            ['name' => 'Sugar Level', 'values' => ['No Sugar', 'Less Sugar', 'Normal', 'Extra Sweet'], 'adjustments' => [0, 0, 0, 1000]],
            ['name' => 'Milk Type', 'values' => ['Regular', 'Oat Milk', 'Almond Milk'], 'adjustments' => [0, 8000, 6000]],
        ];

        $option = $this->faker->randomElement($options);
        $valueIndex = $this->faker->numberBetween(0, count($option['values']) - 1);

        return [
            'name' => $option['name'],
            'value' => $option['values'][$valueIndex],
            'price_adjustment' => $option['adjustments'][$valueIndex],
            'is_active' => $this->faker->boolean(90),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
