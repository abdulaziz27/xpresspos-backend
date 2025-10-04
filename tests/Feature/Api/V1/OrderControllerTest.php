<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create owner role with all permissions
        $ownerRole = Role::create(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo($permissions);

        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        $this->user->assignRole('owner');
        
        $this->category = Category::factory()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'price' => 10000,
            'track_inventory' => true,
            'stock' => 100,
            'status' => true, // Ensure product is active
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_orders()
    {
        // Create some orders
        Order::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'order_number',
                        'status',
                        'total_amount',
                        'created_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ]
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_order_without_items()
    {
        $orderData = [
            'status' => 'draft',
            'notes' => 'Test order',
        ];

        $response = $this->postJson('/api/v1/orders', $orderData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'draft',
                    'notes' => 'Test order',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
            'notes' => 'Test order',
        ]);
    }

    public function test_can_create_order_with_items()
    {
        $orderData = [
            'status' => 'open',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'notes' => 'Extra spicy',
                ]
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $orderData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'open',
                    'total_items' => 2,
                ],
            ]);

        $order = Order::where('store_id', $this->store->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(1, $order->items()->count());
        $this->assertEquals(2, $order->items()->first()->quantity);
    }

    public function test_can_create_order_with_product_options()
    {
        $option = ProductOption::factory()->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'price_adjustment' => 2000,
            'is_active' => true,
        ]);

        $orderData = [
            'status' => 'open',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'product_options' => [$option->id],
                ]
            ],
        ];

        $response = $this->postJson('/api/v1/orders', $orderData);

        $response->assertStatus(201);

        $order = Order::where('store_id', $this->store->id)->first();
        $orderItem = $order->items()->first();
        
        $this->assertEquals(12000, $orderItem->unit_price); // 10000 + 2000
        $this->assertNotEmpty($orderItem->product_options);
    }

    public function test_can_show_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);
    }

    public function test_can_update_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $updateData = [
            'status' => 'open',
            'notes' => 'Updated notes',
        ];

        $response = $this->putJson("/api/v1/orders/{$order->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'open',
                    'notes' => 'Updated notes',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'open',
            'notes' => 'Updated notes',
        ]);
    }

    public function test_cannot_update_completed_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        $updateData = [
            'notes' => 'Should not update',
        ];

        $response = $this->putJson("/api/v1/orders/{$order->id}", $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_NOT_MODIFIABLE',
                ],
            ]);
    }

    public function test_can_delete_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->deleteJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order deleted successfully',
            ]);

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);
    }

    public function test_can_add_item_to_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $itemData = [
            'product_id' => $this->product->id,
            'quantity' => 3,
            'notes' => 'No onions',
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/items", $itemData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'notes' => 'No onions',
                ],
            ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);
    }

    public function test_can_update_order_item()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $orderItem = OrderItem::factory()
            ->forOrder($order)
            ->forProduct($this->product)
            ->create([
                'quantity' => 2,
            ]);

        $updateData = [
            'quantity' => 5,
            'notes' => 'Updated notes',
        ];

        $response = $this->putJson("/api/v1/orders/{$order->id}/items/{$orderItem->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'quantity' => 5,
                    'notes' => 'Updated notes',
                ],
            ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity' => 5,
            'notes' => 'Updated notes',
        ]);
    }

    public function test_can_remove_item_from_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $orderItem = OrderItem::factory()
            ->forOrder($order)
            ->forProduct($this->product)
            ->create();

        $response = $this->deleteJson("/api/v1/orders/{$order->id}/items/{$orderItem->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item removed from order successfully',
            ]);

        $this->assertDatabaseMissing('order_items', [
            'id' => $orderItem->id,
        ]);
    }

    public function test_can_complete_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        // Add an item to the order
        OrderItem::factory()
            ->forOrder($order)
            ->forProduct($this->product)
            ->create();

        $response = $this->postJson("/api/v1/orders/{$order->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_cannot_complete_empty_order()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $response = $this->postJson("/api/v1/orders/{$order->id}/complete");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'ORDER_EMPTY',
                ],
            ]);
    }

    public function test_can_get_order_summary()
    {
        // Create some orders for today
        $completedOrder = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'total_amount' => 50000,
            'total_items' => 3,
            'created_at' => now(),
        ]);

        $openOrder = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'total_amount' => 30000,
            'total_items' => 2,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/orders-summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_orders',
                    'completed_orders',
                    'open_orders',
                    'draft_orders',
                    'total_revenue',
                    'average_order_value',
                    'total_items_sold',
                ],
                'meta' => [
                    'date',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_orders' => 2,
                    'completed_orders' => 1,
                    'open_orders' => 1,
                    'total_revenue' => 50000,
                    'total_items_sold' => 3,
                ],
            ]);
    }

    public function test_validates_required_fields_when_creating_order()
    {
        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                [
                    'quantity' => 2, // Missing product_id
                ]
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_validates_product_exists_when_adding_item()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $itemData = [
            'product_id' => 'invalid-id',
            'quantity' => 1,
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/items", $itemData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_cannot_access_orders_from_different_store()
    {
        $otherStore = Store::factory()->create();
        $otherUser = User::factory()->create(['store_id' => $otherStore->id]);
        $otherUser->assignRole('owner');

        $order = Order::factory()->create([
            'store_id' => $otherStore->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(404);
    }

    public function test_inventory_is_updated_when_adding_items()
    {
        $initialStock = $this->product->stock;

        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $itemData = [
            'product_id' => $this->product->id,
            'quantity' => 5,
        ];

        $response = $this->postJson("/api/v1/orders/{$order->id}/items", $itemData);

        $response->assertStatus(201);

        $this->assertEquals($initialStock - 5, $this->product->fresh()->stock);
    }

    public function test_inventory_is_restored_when_removing_items()
    {
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $orderItem = OrderItem::factory()
            ->forOrder($order)
            ->forProduct($this->product)
            ->create([
                'quantity' => 3,
            ]);

        // Manually reduce stock to simulate the item was added
        $this->product->reduceStock(3);
        $stockAfterAdd = $this->product->fresh()->stock;

        $response = $this->deleteJson("/api/v1/orders/{$order->id}/items/{$orderItem->id}");

        $response->assertStatus(200);

        $this->assertEquals($stockAfterAdd + 3, $this->product->fresh()->stock);
    }
}