<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Services\PlanLimitValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanLimitValidationService $service;
    private Store $store;
    private Plan $basicPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new PlanLimitValidationService();
        
        // Create test store
        $this->store = Store::factory()->create();
        
        // Create test plans
        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'features' => ['pos', 'basic_reports', 'products', 'transactions'],
            'limits' => ['products' => 20, 'users' => 2, 'transactions' => 12000],
        ]);
        
        $this->proPlan = Plan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'features' => ['pos', 'basic_reports', 'inventory_tracking', 'products', 'transactions'],
            'limits' => ['products' => 300, 'users' => 10, 'transactions' => 120000],
        ]);
    }

    public function test_can_perform_action_without_subscription()
    {
        $result = $this->service->canPerformAction($this->store, 'products');

        $this->assertFalse($result['allowed']);
        $this->assertEquals('no_subscription', $result['reason']);
    }

    public function test_can_perform_action_with_expired_subscription()
    {
        // Create expired subscription
        $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'amount' => 99.00,
        ]);

        $result = $this->service->canPerformAction($this->store, 'products');

        $this->assertFalse($result['allowed']);
        $this->assertEquals('subscription_expired', $result['reason']);
    }

    public function test_can_perform_action_with_unavailable_feature()
    {
        // Create active subscription with basic plan
        $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $result = $this->service->canPerformAction($this->store, 'inventory_tracking');

        $this->assertFalse($result['allowed']);
        $this->assertEquals('feature_not_available', $result['reason']);
        $this->assertEquals('Pro', $result['required_plan']);
    }

    public function test_can_perform_action_within_limits()
    {
        // Create active subscription with basic plan
        $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $result = $this->service->canPerformAction($this->store, 'products');

        $this->assertTrue($result['allowed']);
        $this->assertEquals('within_limits', $result['reason']);
    }

    public function test_can_perform_action_exceeding_hard_limit()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products at the limit
        \App\Models\Product::factory()->count(20)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $result = $this->service->canPerformAction($this->store, 'products', 1);

        $this->assertFalse($result['allowed']);
        $this->assertEquals('limit_exceeded', $result['reason']);
        $this->assertEquals(20, $result['current_usage']);
        $this->assertEquals(20, $result['limit']);
    }

    public function test_can_perform_action_with_unlimited_feature()
    {
        // Create enterprise plan with unlimited products
        $enterprisePlan = Plan::factory()->create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'features' => ['pos', 'basic_reports', 'inventory_tracking', 'products'],
            'limits' => ['products' => null, 'users' => null], // Unlimited
        ]);

        // Create active subscription with enterprise plan
        $this->store->subscriptions()->create([
            'plan_id' => $enterprisePlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 399.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create many products
        \App\Models\Product::factory()->count(1000)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $result = $this->service->canPerformAction($this->store, 'products', 1);

        $this->assertTrue($result['allowed']);
        $this->assertEquals('within_limits', $result['reason']);
    }

    public function test_increment_transaction_usage()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create usage record
        $usage = $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 5000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $result = $this->service->incrementUsage($this->store, 'transactions', 10);

        $this->assertTrue($result['success']);
        $this->assertEquals(5000, $result['old_usage']);
        $this->assertEquals(5010, $result['new_usage']);
        $this->assertFalse($result['quota_exceeded']);
    }

    public function test_increment_transaction_usage_exceeding_quota()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create usage record near quota
        $usage = $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 11995,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $result = $this->service->incrementUsage($this->store, 'transactions', 10);

        $this->assertTrue($result['success']);
        $this->assertEquals(11995, $result['old_usage']);
        $this->assertEquals(12005, $result['new_usage']);
        $this->assertTrue($result['quota_exceeded']);
        $this->assertTrue($result['soft_cap_triggered']);
    }

    public function test_get_usage_summary()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create some products
        \App\Models\Product::factory()->count(15)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        // Create usage record
        $usage = $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 8000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $summary = $this->service->getUsageSummary($this->store);

        $this->assertEquals('active', $summary['subscription_status']);
        $this->assertEquals('Basic', $summary['plan_name']);
        
        // Check products feature
        $this->assertEquals(15, $summary['features']['products']['current_usage']);
        $this->assertEquals(20, $summary['features']['products']['limit']);
        $this->assertEquals(75, $summary['features']['products']['usage_percentage']);
        $this->assertEquals('within_limits', $summary['features']['products']['status']);
        
        // Check transactions feature
        $this->assertEquals(8000, $summary['features']['transactions']['current_usage']);
        $this->assertEquals(12000, $summary['features']['transactions']['annual_quota']);
        $this->assertFalse($summary['features']['transactions']['quota_exceeded']);
    }

    public function test_get_usage_summary_with_exceeded_limits()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products exceeding limit
        \App\Models\Product::factory()->count(25)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $summary = $this->service->getUsageSummary($this->store);

        $this->assertEquals('limit_exceeded', $summary['features']['products']['status']);
        $this->assertEquals(25, $summary['features']['products']['current_usage']);
        $this->assertEquals(20, $summary['features']['products']['limit']);
    }

    public function test_get_usage_summary_approaching_limits()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products approaching limit (16 out of 20 = 80%)
        \App\Models\Product::factory()->count(16)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $summary = $this->service->getUsageSummary($this->store);

        $this->assertEquals('approaching_limit', $summary['features']['products']['status']);
        $this->assertEquals(80, $summary['features']['products']['usage_percentage']);
    }

    public function test_reset_annual_usage()
    {
        // Create active subscription with basic plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'annual',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'amount' => 990.00,
        ]);

        // Create usage record with high usage
        $usage = $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 10000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->subYear()->startOfYear(),
            'subscription_year_end' => now()->subYear()->endOfYear(),
            'soft_cap_triggered' => true,
            'soft_cap_triggered_at' => now()->subMonths(2),
        ]);

        $result = $this->service->resetAnnualUsage($subscription);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['reset_count']);
        $this->assertEmpty($result['errors']);

        // Check that usage was reset
        $usage->refresh();
        $this->assertEquals(0, $usage->current_usage);
        $this->assertFalse($usage->soft_cap_triggered);
        $this->assertNull($usage->soft_cap_triggered_at);
    }

    public function test_get_stores_needing_attention()
    {
        // Create store with issues
        $storeWithIssues = Store::factory()->create();
        $subscription = $storeWithIssues->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create a category first
        $category = $storeWithIssues->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products exceeding limit
        \App\Models\Product::factory()->count(25)->create([
            'store_id' => $storeWithIssues->id,
            'category_id' => $category->id,
        ]);

        // Create transaction usage exceeding quota
        $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 13000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        // Create healthy store
        $healthyStore = Store::factory()->create();
        $healthyStore->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $storesNeedingAttention = $this->service->getStoresNeedingAttention();

        $this->assertCount(1, $storesNeedingAttention);
        $this->assertEquals($storeWithIssues->id, $storesNeedingAttention[0]['store']->id);
        $this->assertCount(2, $storesNeedingAttention[0]['issues']); // products limit exceeded + transaction quota exceeded
        
        // Check issue types
        $issueTypes = collect($storesNeedingAttention[0]['issues'])->pluck('type')->toArray();
        $this->assertContains('limit_exceeded', $issueTypes);
        $this->assertContains('quota_exceeded', $issueTypes);
    }
}