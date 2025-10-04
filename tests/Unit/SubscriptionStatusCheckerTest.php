<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Services\SubscriptionStatusChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionStatusCheckerTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionStatusChecker $statusChecker;
    private Store $store;
    private Plan $basicPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->statusChecker = new SubscriptionStatusChecker();
        
        // Create test store
        $this->store = Store::factory()->create();
        
        // Create test plans
        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'features' => ['pos', 'basic_reports'],
            'limits' => ['products' => 20, 'users' => 2, 'transactions' => 12000],
        ]);
        
        $this->proPlan = Plan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'features' => ['pos', 'basic_reports', 'inventory_tracking'],
            'limits' => ['products' => 300, 'users' => 10, 'transactions' => 120000],
        ]);
    }

    public function test_check_store_subscription_with_no_subscription()
    {
        $result = $this->statusChecker->checkStoreSubscription($this->store);

        $this->assertEquals('no_subscription', $result['status']);
        $this->assertTrue($result['requires_action']);
        $this->assertEquals('Store has no active subscription', $result['message']);
    }

    public function test_check_store_subscription_with_active_subscription()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $result = $this->statusChecker->checkStoreSubscription($this->store);

        $this->assertEquals('active', $result['status']);
        $this->assertFalse($result['requires_action']);
        $this->assertEquals('Subscription is active', $result['message']);
    }

    public function test_check_store_subscription_with_expired_subscription()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'amount' => 99.00,
        ]);

        $result = $this->statusChecker->checkStoreSubscription($this->store);

        $this->assertEquals('expired', $result['status']);
        $this->assertTrue($result['requires_action']);
        $this->assertEquals('Subscription has expired', $result['message']);
        
        // Check that subscription status was updated
        $subscription->refresh();
        $this->assertEquals('expired', $subscription->status);
    }

    public function test_check_store_subscription_expiring_soon()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDays(25),
            'ends_at' => now()->addDays(5),
            'amount' => 99.00,
        ]);

        $result = $this->statusChecker->checkStoreSubscription($this->store);

        $this->assertEquals('expiring_soon', $result['status']);
        $this->assertTrue($result['requires_action']);
        $this->assertStringContainsString('expires in', $result['message']);
        $this->assertGreaterThanOrEqual(4, $result['days_remaining']);
        $this->assertLessThanOrEqual(5, $result['days_remaining']);
    }

    public function test_check_store_subscription_on_trial()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'trial_ends_at' => now()->addDays(10),
            'amount' => 99.00,
        ]);

        $result = $this->statusChecker->checkStoreSubscription($this->store);

        $this->assertEquals('on_trial', $result['status']);
        $this->assertFalse($result['requires_action']); // More than 3 days remaining
        $this->assertStringContainsString('Trial period active', $result['message']);
        $this->assertGreaterThanOrEqual(9, $result['trial_days_remaining']);
        $this->assertLessThanOrEqual(10, $result['trial_days_remaining']);
    }

    public function test_can_access_feature_with_valid_subscription()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199.00,
        ]);

        $result = $this->statusChecker->canAccessFeature($this->store, 'inventory_tracking');

        $this->assertTrue($result['can_access']);
        $this->assertEquals('feature_available', $result['reason']);
        $this->assertEquals('Feature is available', $result['message']);
    }

    public function test_cannot_access_feature_without_subscription()
    {
        $result = $this->statusChecker->canAccessFeature($this->store, 'inventory_tracking');

        $this->assertFalse($result['can_access']);
        $this->assertEquals('no_subscription', $result['reason']);
        $this->assertEquals('No active subscription found', $result['message']);
    }

    public function test_cannot_access_feature_with_expired_subscription()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'amount' => 199.00,
        ]);

        $result = $this->statusChecker->canAccessFeature($this->store, 'inventory_tracking');

        $this->assertFalse($result['can_access']);
        $this->assertEquals('subscription_expired', $result['reason']);
        $this->assertEquals('Subscription has expired', $result['message']);
    }

    public function test_cannot_access_feature_not_in_plan()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $result = $this->statusChecker->canAccessFeature($this->store, 'inventory_tracking');

        $this->assertFalse($result['can_access']);
        $this->assertEquals('feature_not_available', $result['reason']);
        $this->assertStringContainsString('Feature requires Pro plan', $result['message']);
        $this->assertEquals('Pro', $result['required_plan']);
    }

    public function test_check_usage_limits_within_limits()
    {
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
        
        // Create some products (within limit)
        $this->store->products()->createMany([
            ['name' => 'Product 1', 'sku' => 'SKU1', 'price' => 10.00, 'category_id' => $category->id],
            ['name' => 'Product 2', 'sku' => 'SKU2', 'price' => 20.00, 'category_id' => $category->id],
        ]);

        $result = $this->statusChecker->checkUsageLimits($this->store);

        $this->assertEquals('within_limits', $result['status']);
        $this->assertEmpty($result['violations']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_check_usage_limits_with_violations()
    {
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
        
        // Create products that exceed limit
        $this->store->products()->createMany(
            collect(range(1, 25))->map(fn($i) => [
                'name' => "Product {$i}",
                'sku' => "SKU{$i}",
                'price' => 10.00,
                'category_id' => $category->id,
            ])->toArray()
        );

        $result = $this->statusChecker->checkUsageLimits($this->store);

        $this->assertEquals('exceeded_limits', $result['status']);
        $this->assertNotEmpty($result['violations']);
        $this->assertEquals('products', $result['violations'][0]['feature']);
        $this->assertEquals(25, $result['violations'][0]['current_usage']);
        $this->assertEquals(20, $result['violations'][0]['limit']);
    }

    public function test_check_transaction_quota_within_limit()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create usage record
        $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 5000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $result = $this->statusChecker->checkTransactionQuota($this->store);

        $this->assertEquals('within_limit', $result['status']);
        $this->assertEquals(5000, $result['current_usage']);
        $this->assertEquals(12000, $result['annual_quota']);
        $this->assertLessThan(80, $result['percentage']);
    }

    public function test_check_transaction_quota_with_warning()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create usage record at 85% of quota
        $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 10200,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $result = $this->statusChecker->checkTransactionQuota($this->store);

        $this->assertEquals('warning', $result['status']);
        $this->assertEquals('Approaching transaction quota limit', $result['message']);
        $this->assertEquals(10200, $result['current_usage']);
        $this->assertGreaterThan(80, $result['percentage']);
    }

    public function test_check_transaction_quota_exceeded()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        // Create usage record that exceeds quota
        $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 13000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $result = $this->statusChecker->checkTransactionQuota($this->store);

        $this->assertEquals('exceeded', $result['status']);
        $this->assertEquals('Annual transaction quota exceeded', $result['message']);
        $this->assertEquals(13000, $result['current_usage']);
        $this->assertGreaterThan(100, $result['percentage']);
    }

    public function test_get_subscription_health_summary()
    {
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $health = $this->statusChecker->getSubscriptionHealth($this->store);

        $this->assertArrayHasKey('subscription', $health);
        $this->assertArrayHasKey('usage_limits', $health);
        $this->assertArrayHasKey('feature_access', $health);
        $this->assertArrayHasKey('overall_health', $health);
        
        $this->assertEquals('active', $health['subscription']['status']);
        $this->assertEquals('healthy', $health['overall_health']);
        
        // Check feature access
        $this->assertArrayHasKey('inventory_tracking', $health['feature_access']);
        $this->assertFalse($health['feature_access']['inventory_tracking']['can_access']);
    }

    public function test_get_stores_requiring_attention()
    {
        // Create store with expiring subscription
        $expiringStore = Store::factory()->create();
        $expiringStore->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDays(25),
            'ends_at' => now()->addDays(5),
            'amount' => 99.00,
        ]);

        // Create store with healthy subscription
        $healthyStore = Store::factory()->create();
        $healthyStore->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $storesRequiringAttention = $this->statusChecker->getStoresRequiringAttention();

        $this->assertCount(1, $storesRequiringAttention);
        $this->assertEquals($expiringStore->id, $storesRequiringAttention->first()->id);
    }
}