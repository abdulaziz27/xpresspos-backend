<?php

namespace Tests\Feature\Feature\Api\V1;

use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockLevel;
use App\Models\InventoryMovement;
use App\Models\CogsHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class InventoryReportControllerTest extends TestCase
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
            'price' => 25.00,
        ]);

        // Create stock level
        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'current_stock' => 100,
            'available_stock' => 100,
            'average_cost' => 15.00,
            'total_value' => 1500.00,
            'last_movement_at' => now()->subDays(5),
        ]);

        // Create some inventory movements
        InventoryMovement::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'type' => 'sale',
            'quantity' => 5,
            'unit_cost' => 15.00,
        ]);

        // Create COGS history
        CogsHistory::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'quantity_sold' => 5,
            'unit_cost' => 15.00,
            'total_cogs' => 75.00,
        ]);
    }

    public function test_can_get_stock_levels_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/stock-levels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stock_levels' => [
                        '*' => [
                            'id',
                            'current_stock',
                            'available_stock',
                            'total_value',
                            'product' => [
                                'id',
                                'name',
                                'sku',
                            ]
                        ]
                    ],
                    'summary' => [
                        'total_products',
                        'total_stock_value',
                        'low_stock_count',
                        'out_of_stock_count',
                        'total_items',
                    ]
                ]
            ]);
    }

    public function test_can_get_inventory_movements_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/movements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'movements' => [
                        '*' => [
                            'id',
                            'type',
                            'quantity',
                            'product',
                            'user',
                        ]
                    ],
                    'summary',
                    'period',
                ]
            ]);
    }

    public function test_can_get_inventory_valuation_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/valuation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'valuation' => [
                        'total_value',
                        'total_items',
                        'products_count',
                    ],
                    'method',
                    'generated_at',
                ]
            ]);
    }

    public function test_can_get_cogs_analysis_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/cogs-analysis');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cogs_summary' => [
                        'total_cogs',
                        'total_quantity_sold',
                        'average_unit_cost',
                    ],
                    'profit_analysis',
                    'period',
                ]
            ]);
    }

    public function test_can_get_stock_aging_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/stock-aging');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'aging_groups',
                    'summary' => [
                        'total_products',
                        'total_stock_value',
                        'average_age_days',
                        'oldest_stock_days',
                    ],
                    'generated_at',
                ]
            ]);
    }

    public function test_can_get_stock_turnover_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/stock-turnover');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'turnover_analysis',
                    'turnover_categories',
                    'summary' => [
                        'total_products',
                        'average_turnover_ratio',
                        'average_days_of_supply',
                        'period_months',
                    ],
                    'period',
                ]
            ]);
    }

    public function test_can_filter_stock_levels_by_category()
    {
        $category2 = Category::factory()->create(['store_id' => $this->store->id]);
        $product2 = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category2->id,
            'track_inventory' => true,
        ]);

        StockLevel::create([
            'store_id' => $this->store->id,
            'product_id' => $product2->id,
            'current_stock' => 50,
            'available_stock' => 50,
            'average_cost' => 20.00,
            'total_value' => 1000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/inventory/reports/stock-levels?category_id={$category2->id}");

        $response->assertStatus(200);
        
        $stockLevels = $response->json('data.stock_levels');
        $this->assertCount(1, $stockLevels);
        $this->assertEquals($product2->id, $stockLevels[0]['product']['id']);
    }

    public function test_can_filter_movements_by_date_range()
    {
        $dateFrom = Carbon::now()->subDays(7)->toDateString();
        $dateTo = Carbon::now()->toDateString();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/inventory/reports/movements?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200)
            ->assertJsonPath('data.period.from', $dateFrom . 'T00:00:00.000000Z')
            ->assertJsonPath('data.period.to', $dateTo . 'T23:59:59.999999Z');
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/v1/inventory/reports/stock-levels');
        $response->assertStatus(401);
    }

    public function test_validates_date_range_in_movements_report()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/inventory/reports/movements?date_from=2024-12-01&date_to=2024-11-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }
}
