<?php

namespace Tests\Feature\Feature\Api\V1;

use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Store $store;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and roles
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create test store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        $this->user->assignRole('owner');
        
        // Create Pro plan with inventory tracking feature
        $plan = \App\Models\Plan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'features' => ['pos', 'inventory_tracking', 'advanced_reports', 'cogs_calculation'],
            'limits' => ['products' => 500, 'users' => 5, 'transactions' => 5000],
        ]);
        
        // Create active subscription for the store
        \App\Models\Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
        
        // Create test category and product
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'track_inventory' => true,
            'stock' => 100,
            'min_stock_level' => 10,
        ]);

        // Create stock level
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 100,
            'available_stock' => 100,
            'average_cost' => 10.00,
            'total_value' => 1000.00,
        ]);
    }

    public function test_can_get_inventory_list()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'current_stock',
                            'available_stock',
                            'product' => [
                                'id',
                                'name',
                                'sku',
                            ]
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_get_specific_product_inventory()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/inventory/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'product',
                    'stock_level',
                    'recent_movements',
                    'is_low_stock',
                    'is_out_of_stock',
                ]
            ]);
    }

    public function test_can_adjust_stock()
    {
        $adjustmentData = [
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_cost' => 12.00,
            'reason' => 'Stock replenishment',
            'notes' => 'Received new shipment',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/inventory/adjust', $adjustmentData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'movement',
                    'stock_level',
                    'product',
                ]
            ]);

        // Verify movement was created
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'type' => 'adjustment_in',
            'quantity' => 50,
            'reason' => 'Stock replenishment',
        ]);
    }

    public function test_can_get_inventory_movements()
    {
        // Create some test movements
        InventoryMovement::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/movements/list');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'quantity',
                            'product',
                            'user',
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_get_low_stock_alerts()
    {
        // Create a low stock product
        $lowStockProduct = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => Category::factory()->create(['store_id' => $this->store->id])->id,
            'track_inventory' => true,
            'min_stock_level' => 20,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $lowStockProduct->id,
            'current_stock' => 5, // Below minimum
            'available_stock' => 5,
            'average_cost' => 10.00,
            'total_value' => 50.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/alerts/low-stock');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'low_stock_count',
                    'products',
                ]
            ]);

        $this->assertTrue($response->json('data.low_stock_count') > 0);
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/inventory');
        $response->assertStatus(401);
    }

    public function test_validates_stock_adjustment_data()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/inventory/adjust', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'quantity', 'reason']);
    }
}
