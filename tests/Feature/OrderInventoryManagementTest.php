<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderInventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Category $category;
    protected Product $productTracked;
    protected Product $productNotTracked;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and store
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
        ]);
        
        // Assign user to store
        $this->user->stores()->attach($this->store->id, [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        // Authenticate user
        Sanctum::actingAs($this->user);

        // Create category
        $this->category = Category::factory()->create([
            'store_id' => $this->store->id,
        ]);

        // Create products
        $this->productTracked = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'stock' => 100,
            'track_inventory' => true,
            'status' => true,
        ]);

        $this->productNotTracked = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $this->category->id,
            'stock' => 100,
            'track_inventory' => false,
            'status' => true,
        ]);
    }

    /**
     * Test creating order deducts inventory when flag is true.
     */
    public function test_create_order_deducts_inventory(): void
    {
        $initialStock = $this->productTracked->stock;

        $response = $this->postJson('/api/v1/orders', [
            'operation_mode' => 'dine_in',
            'payment_mode' => 'open_bill',
            'status' => 'open',
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'quantity' => 10,
                ]
            ],
            'deduct_inventory' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully',
            ]);

        // Verify stock decreased
        $this->productTracked->refresh();
        $this->assertEquals($initialStock - 10, $this->productTracked->stock);
    }

    /**
     * Test creating order without deduct flag doesn't change inventory.
     */
    public function test_create_order_without_deduct_flag_preserves_inventory(): void
    {
        $initialStock = $this->productTracked->stock;

        $response = $this->postJson('/api/v1/orders', [
            'operation_mode' => 'dine_in',
            'payment_mode' => 'open_bill',
            'status' => 'open',
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'quantity' => 10,
                ]
            ],
            'deduct_inventory' => false,
        ]);

        $response->assertStatus(201);

        // Verify stock unchanged
        $this->productTracked->refresh();
        $this->assertEquals($initialStock, $this->productTracked->stock);
    }

    /**
     * Test updating order adjusts inventory.
     */
    public function test_update_order_adjusts_inventory(): void
    {
        // Create order with items
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $order->items()->create([
            'product_id' => $this->productTracked->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
            'product_name' => $this->productTracked->name,
        ]);

        // Manually deduct stock
        $this->productTracked->decrement('stock', 10);
        $initialStock = $this->productTracked->stock;

        // Update order with different items
        $response = $this->putJson("/api/v1/orders/{$order->id}", [
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'quantity' => 15, // Changed from 10 to 15
                ]
            ],
            'update_inventory' => true,
        ]);

        $response->assertStatus(200);

        // Verify stock: restored 10, deducted 15, net = -5
        $this->productTracked->refresh();
        $this->assertEquals($initialStock - 5, $this->productTracked->stock);
    }

    /**
     * Test cancelling order restores inventory.
     */
    public function test_cancel_order_restores_inventory(): void
    {
        // Create order with items
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $order->items()->create([
            'product_id' => $this->productTracked->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
            'product_name' => $this->productTracked->name,
        ]);

        // Manually deduct stock
        $this->productTracked->decrement('stock', 10);
        $stockBeforeCancel = $this->productTracked->stock;

        // Cancel order with restore inventory
        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [
            'restore_inventory' => true,
            'reason' => 'Customer changed mind',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'meta' => [
                    'inventory_restored' => true,
                ],
            ]);

        // Verify stock restored
        $this->productTracked->refresh();
        $this->assertEquals($stockBeforeCancel + 10, $this->productTracked->stock);

        // Verify order is cancelled
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
    }

    /**
     * Test inventory operations only affect tracked products.
     */
    public function test_inventory_only_affects_tracked_products(): void
    {
        $initialStockTracked = $this->productTracked->stock;
        $initialStockNotTracked = $this->productNotTracked->stock;

        $response = $this->postJson('/api/v1/orders', [
            'operation_mode' => 'dine_in',
            'status' => 'open',
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'quantity' => 10,
                ],
                [
                    'product_id' => $this->productNotTracked->id,
                    'quantity' => 5,
                ]
            ],
            'deduct_inventory' => true,
        ]);

        $response->assertStatus(201);

        // Verify only tracked product stock decreased
        $this->productTracked->refresh();
        $this->productNotTracked->refresh();
        
        $this->assertEquals($initialStockTracked - 10, $this->productTracked->stock);
        $this->assertEquals($initialStockNotTracked, $this->productNotTracked->stock);
    }

    /**
     * Test insufficient stock throws error.
     */
    public function test_insufficient_stock_prevents_order_creation(): void
    {
        // Set low stock
        $this->productTracked->update(['stock' => 5]);

        $response = $this->postJson('/api/v1/orders', [
            'operation_mode' => 'dine_in',
            'status' => 'open',
            'items' => [
                [
                    'product_id' => $this->productTracked->id,
                    'quantity' => 10, // More than available
                ]
            ],
            'deduct_inventory' => true,
        ]);

        $response->assertStatus(500);

        // Verify stock unchanged
        $this->productTracked->refresh();
        $this->assertEquals(5, $this->productTracked->stock);
    }

    /**
     * Test cancel without restore flag doesn't restore inventory.
     */
    public function test_cancel_without_restore_flag_preserves_inventory(): void
    {
        // Create order with items
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $order->items()->create([
            'product_id' => $this->productTracked->id,
            'quantity' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
            'product_name' => $this->productTracked->name,
        ]);

        // Manually deduct stock
        $this->productTracked->decrement('stock', 10);
        $stockBeforeCancel = $this->productTracked->stock;

        // Cancel order without restore inventory
        $response = $this->postJson("/api/v1/orders/{$order->id}/cancel", [
            'restore_inventory' => false,
        ]);

        $response->assertStatus(200);

        // Verify stock unchanged
        $this->productTracked->refresh();
        $this->assertEquals($stockBeforeCancel, $this->productTracked->stock);
    }
}

