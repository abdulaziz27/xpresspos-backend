<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\CashSession;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class ReportingSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create([
            'store_id' => $this->store->id,
        ]);
        
        // Create subscription with Pro plan
        $plan = Plan::factory()->create([
            'name' => 'Pro Test',
            'slug' => 'pro-test',
            'features' => ['inventory_tracking', 'advanced_reports', 'report_export'],
        ]);
        
        Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_get_dashboard_summary()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/dashboard?period=today');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_period' => [
                        'revenue',
                        'profit',
                        'expenses',
                        'orders',
                        'average_order_value',
                        'customers',
                    ],
                    'previous_period',
                    'growth',
                    'period',
                    'date_range',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_sales_report()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/sales?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
            'group_by' => 'day',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_orders',
                        'total_revenue',
                        'total_items',
                        'average_order_value',
                        'unique_customers',
                    ],
                    'timeline',
                    'payment_methods',
                    'top_products',
                    'period',
                    'filters',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_inventory_report()
    {
        // Create test products
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        Product::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'track_inventory' => true,
        ]);

        $response = $this->getJson('/api/v1/reports/inventory');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_products',
                        'low_stock_products',
                        'out_of_stock_products',
                        'total_stock_value',
                        'average_stock_level',
                    ],
                    'products',
                    'filters',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_cash_flow_report()
    {
        // Create test data
        $this->createTestCashFlow();

        $response = $this->getJson('/api/v1/reports/cash-flow?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_revenue',
                        'total_expenses',
                        'net_cash_flow',
                        'transaction_count',
                        'expense_count',
                        'average_transaction',
                        'average_expense',
                    ],
                    'daily_flow',
                    'payment_methods',
                    'expense_categories',
                    'period',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_product_performance_report()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/product-performance?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
            'limit' => 10,
            'sort_by' => 'revenue',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_products_sold',
                        'total_quantity',
                        'total_revenue',
                        'total_profit',
                        'average_profit_margin',
                    ],
                    'products',
                    'period',
                    'sort_by',
                    'limit',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_customer_analytics_report()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/customer-analytics?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_orders',
                        'unique_customers',
                        'guest_orders',
                        'member_orders',
                        'member_percentage',
                        'total_revenue',
                        'average_order_value',
                    ],
                    'top_customers',
                    'segments',
                    'period',
                ],
                'meta',
            ]);
    }

    public function test_can_queue_report_export()
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports/export', [
            'report_type' => 'sales',
            'format' => 'pdf',
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'job_id',
                    'message',
                ],
            ]);

        Queue::assertPushed(\App\Jobs\ExportReportJob::class);
    }

    public function test_export_blocked_when_quota_exceeded_without_premium_plan()
    {
        // Create Basic plan without export feature
        $basicPlan = Plan::factory()->create([
            'name' => 'Basic Test',
            'slug' => 'basic-test',
            'features' => [],
        ]);
        
        $this->store->activeSubscription->update(['plan_id' => $basicPlan->id]);
        
        // Create subscription usage that exceeds quota
        \App\Models\SubscriptionUsage::factory()->create([
            'subscription_id' => $this->store->activeSubscription->id,
            'feature_type' => 'transactions',
            'current_usage' => 15000,
            'annual_quota' => 12000,
        ]);

        $response = $this->postJson('/api/v1/reports/export', [
            'report_type' => 'sales',
            'format' => 'pdf',
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'QUOTA_EXCEEDED_PREMIUM_BLOCKED',
                ],
            ]);
    }

    public function test_reports_are_cached()
    {
        Cache::flush();
        
        // Create test data
        $this->createTestOrders();

        // First request should generate cache
        $response1 = $this->getJson('/api/v1/reports/sales?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response1->assertOk();

        // Second request should use cache
        $response2 = $this->getJson('/api/v1/reports/sales?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response2->assertOk()
            ->assertJson(['meta' => ['cached' => true]]);
    }

    public function test_validates_report_parameters()
    {
        $response = $this->getJson('/api/v1/reports/sales');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_filters_data_by_store_scope()
    {
        // Create another store with data
        $otherStore = Store::factory()->create();
        $otherUser = User::factory()->create(['store_id' => $otherStore->id]);
        
        // Create orders for other store
        Order::factory()->create([
            'store_id' => $otherStore->id,
            'user_id' => $otherUser->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create orders for current store
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/sales?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk();
        
        // Should only include current store's data
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['summary']['total_orders']);
    }

    public function test_can_generate_sales_trend_analysis()
    {
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/sales-trends?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
            'group_by' => 'day',
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'historical_data',
                    'trends' => [
                        'trend',
                        'slope',
                        'correlation',
                        'strength',
                    ],
                    'forecast',
                    'seasonality',
                    'insights',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_product_analytics()
    {
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/product-analytics?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'abc_analysis' => [
                        'categories',
                        'summary',
                    ],
                    'lifecycle_analysis',
                    'cross_selling',
                    'price_elasticity',
                    'recommendations',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_customer_behavior_analytics()
    {
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/customer-behavior?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'rfm_analysis',
                    'customer_lifetime_value',
                    'churn_analysis',
                    'purchase_patterns',
                    'customer_journey',
                    'segments',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_profitability_analysis()
    {
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/profitability-analysis?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'gross_margins',
                    'cost_analysis',
                    'profit_centers',
                    'break_even',
                    'profitability_trends',
                ],
                'meta',
            ]);
    }

    public function test_can_generate_operational_efficiency_metrics()
    {
        $this->createTestOrders();

        $response = $this->getJson('/api/v1/reports/operational-efficiency?' . http_build_query([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'staff_performance',
                    'peak_hours',
                    'table_turnover',
                    'service_efficiency',
                    'efficiency_recommendations',
                ],
                'meta',
            ]);
    }

    private function createTestOrders(): void
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $products = Product::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $orders = Order::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'completed_at' => now()->subDays(rand(1, 3)), // Within the last 3 days
            'total_amount' => 100.00,
        ]);

        foreach ($orders as $order) {
            // Create payments for each order
            Payment::factory()->create([
                'store_id' => $this->store->id,
                'order_id' => $order->id,
                'amount' => $order->total_amount,
                'status' => 'completed',
                'created_at' => $order->completed_at,
            ]);
        }
    }

    private function createTestCashFlow(): void
    {
        // Create cash session
        $cashSession = CashSession::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'closed',
        ]);

        // Create expenses
        Expense::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'cash_session_id' => $cashSession->id,
            'user_id' => $this->user->id,
            'expense_date' => now()->subDays(rand(1, 7)),
        ]);

        // Create payments
        Payment::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => now()->subDays(rand(1, 7)),
        ]);
    }
}