<?php

namespace Database\Factories;

use App\Models\SyncHistory;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncHistory>
 */
class SyncHistoryFactory extends Factory
{
    protected $model = SyncHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $syncTypes = [SyncHistory::TYPE_ORDER, SyncHistory::TYPE_INVENTORY, SyncHistory::TYPE_PAYMENT, SyncHistory::TYPE_PRODUCT];
        $operations = [SyncHistory::OPERATION_CREATE, SyncHistory::OPERATION_UPDATE, SyncHistory::OPERATION_DELETE];
        $statuses = [SyncHistory::STATUS_PENDING, SyncHistory::STATUS_COMPLETED, SyncHistory::STATUS_FAILED, SyncHistory::STATUS_CONFLICT];

        return [
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
            'idempotency_key' => $this->faker->uuid(),
            'sync_type' => $this->faker->randomElement($syncTypes),
            'operation' => $this->faker->randomElement($operations),
            'entity_type' => $this->faker->randomElement(['Order', 'Product', 'Payment', 'InventoryMovement']),
            'entity_id' => $this->faker->uuid(),
            'payload' => [
                'test_field' => $this->faker->word(),
                'amount' => $this->faker->randomFloat(2, 10, 1000),
                'timestamp' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            ],
            'conflicts' => null,
            'status' => $this->faker->randomElement($statuses),
            'error_message' => null,
            'retry_count' => $this->faker->numberBetween(0, 3),
            'last_retry_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the sync is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncHistory::STATUS_COMPLETED,
            'completed_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the sync failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncHistory::STATUS_FAILED,
            'error_message' => $this->faker->sentence(),
            'retry_count' => $this->faker->numberBetween(1, 5),
            'last_retry_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Indicate that the sync has conflicts.
     */
    public function conflicted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncHistory::STATUS_CONFLICT,
            'conflicts' => [
                [
                    'type' => 'field_conflict',
                    'field' => 'status',
                    'server_value' => 'completed',
                    'client_value' => 'pending',
                    'message' => 'Status field has different values',
                ]
            ],
        ]);
    }

    /**
     * Indicate that the sync is for orders.
     */
    public function forOrders(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => SyncHistory::TYPE_ORDER,
            'entity_type' => 'Order',
            'payload' => [
                'order_number' => 'ORD' . $this->faker->numberBetween(1000, 9999),
                'total_amount' => $this->faker->randomFloat(2, 10, 500),
                'status' => $this->faker->randomElement(['draft', 'open', 'completed']),
                'items' => [
                    [
                        'product_id' => $this->faker->uuid(),
                        'quantity' => $this->faker->numberBetween(1, 5),
                        'unit_price' => $this->faker->randomFloat(2, 5, 50),
                        'total_price' => $this->faker->randomFloat(2, 10, 200),
                    ]
                ]
            ],
        ]);
    }

    /**
     * Indicate that the sync is for inventory.
     */
    public function forInventory(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => SyncHistory::TYPE_INVENTORY,
            'entity_type' => 'InventoryMovement',
            'payload' => [
                'product_id' => $this->faker->uuid(),
                'type' => $this->faker->randomElement(['sale', 'purchase', 'adjustment_in', 'adjustment_out']),
                'quantity' => $this->faker->numberBetween(1, 100),
                'unit_cost' => $this->faker->randomFloat(2, 1, 20),
                'reason' => $this->faker->sentence(),
            ],
        ]);
    }

    /**
     * Indicate that the sync is for payments.
     */
    public function forPayments(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_type' => SyncHistory::TYPE_PAYMENT,
            'entity_type' => 'Payment',
            'payload' => [
                'order_id' => $this->faker->uuid(),
                'amount' => $this->faker->randomFloat(2, 10, 500),
                'method' => $this->faker->randomElement(['cash', 'card', 'qris', 'transfer']),
                'status' => $this->faker->randomElement(['pending', 'completed', 'failed']),
                'reference_number' => $this->faker->regexify('[A-Z0-9]{10}'),
            ],
        ]);
    }
}