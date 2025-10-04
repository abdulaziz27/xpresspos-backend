<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;
    private Store $store;
    private Plan $basicPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriptionService = new SubscriptionService();
        
        // Create test store
        $this->store = Store::factory()->create();
        
        // Create test plans
        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'price' => 99.00,
            'annual_price' => 990.00,
            'features' => ['pos', 'basic_reports'],
            'limits' => ['products' => 20, 'users' => 2, 'transactions' => 12000],
        ]);
        
        $this->proPlan = Plan::factory()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 199.00,
            'annual_price' => 1990.00,
            'features' => ['pos', 'basic_reports', 'inventory_tracking'],
            'limits' => ['products' => 300, 'users' => 10, 'transactions' => 120000],
        ]);
    }

    public function test_can_create_monthly_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan, [
            'billing_cycle' => 'monthly',
        ]);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals($this->store->id, $subscription->store_id);
        $this->assertEquals($this->basicPlan->id, $subscription->plan_id);
        $this->assertEquals('monthly', $subscription->billing_cycle);
        $this->assertEquals(99.00, $subscription->amount);
        $this->assertEquals('active', $subscription->status);
        
        // Check usage tracking was initialized
        $this->assertCount(4, $subscription->usage); // products, transactions, users, outlets
        
        $transactionUsage = $subscription->usage()->where('feature_type', 'transactions')->first();
        $this->assertEquals(12000, $transactionUsage->annual_quota);
    }

    public function test_can_create_annual_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan, [
            'billing_cycle' => 'annual',
        ]);

        $this->assertEquals('annual', $subscription->billing_cycle);
        $this->assertEquals(990.00, $subscription->amount);
        $this->assertEquals(now()->addYear()->format('Y-m-d'), $subscription->ends_at->format('Y-m-d'));
    }

    public function test_can_create_subscription_with_trial()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan, [
            'trial_days' => 14,
        ]);

        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEquals(now()->addDays(14)->format('Y-m-d'), $subscription->trial_ends_at->format('Y-m-d'));
        $this->assertTrue($subscription->onTrial());
    }

    public function test_creating_new_subscription_cancels_existing_active_subscription()
    {
        // Create first subscription
        $firstSubscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        $this->assertEquals('active', $firstSubscription->status);

        // Create second subscription
        $secondSubscription = $this->subscriptionService->createSubscription($this->store, $this->proPlan);

        // First subscription should be cancelled
        $firstSubscription->refresh();
        $this->assertEquals('cancelled', $firstSubscription->status);
        $this->assertEquals('active', $secondSubscription->status);
    }

    public function test_can_upgrade_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        
        $upgradedSubscription = $this->subscriptionService->upgradeSubscription($subscription, $this->proPlan);

        $this->assertEquals($this->proPlan->id, $upgradedSubscription->plan_id);
        $this->assertArrayHasKey('upgraded_from', $upgradedSubscription->metadata);
        $this->assertEquals($this->basicPlan->id, $upgradedSubscription->metadata['upgraded_from']);
    }

    public function test_can_downgrade_subscription()
    {
        // Create a basic plan that has compatible features with pro plan
        $compatibleBasicPlan = Plan::factory()->create([
            'name' => 'Basic Compatible',
            'slug' => 'basic-compatible',
            'price' => 99.00,
            'features' => ['pos', 'basic_reports', 'inventory_tracking'], // Include all pro features to avoid feature validation error
            'limits' => ['products' => 20, 'users' => 2, 'transactions' => 12000],
        ]);
        
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->proPlan);
        
        $downgradedSubscription = $this->subscriptionService->downgradeSubscription($subscription, $compatibleBasicPlan);

        $this->assertArrayHasKey('scheduled_downgrade', $downgradedSubscription->metadata);
        $this->assertEquals($compatibleBasicPlan->id, $downgradedSubscription->metadata['scheduled_downgrade']['plan_id']);
    }

    public function test_downgrade_fails_when_usage_exceeds_new_plan_limits()
    {
        // Create a basic plan that has compatible features with pro plan
        $compatibleBasicPlan = Plan::factory()->create([
            'name' => 'Basic Compatible',
            'slug' => 'basic-compatible',
            'price' => 99.00,
            'features' => ['pos', 'basic_reports', 'inventory_tracking'], // Include all pro features to avoid feature validation error
            'limits' => ['products' => 20, 'users' => 2, 'transactions' => 12000],
        ]);
        
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->proPlan);
        
        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products that exceed basic plan limit using factory
        \App\Models\Product::factory()->count(25)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot downgrade: Current products usage (25) exceeds new plan limit (20)');

        $this->subscriptionService->downgradeSubscription($subscription, $compatibleBasicPlan);
    }

    public function test_can_cancel_subscription_immediately()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        
        $cancelledSubscription = $this->subscriptionService->cancelSubscription($subscription, true);

        $this->assertEquals('cancelled', $cancelledSubscription->status);
        $this->assertEquals(now()->format('Y-m-d'), $cancelledSubscription->ends_at->format('Y-m-d'));
    }

    public function test_can_cancel_subscription_at_end_of_period()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        $originalEndDate = $subscription->ends_at;
        
        $cancelledSubscription = $this->subscriptionService->cancelSubscription($subscription, false);

        $this->assertEquals('active', $cancelledSubscription->status); // Still active until end date
        $this->assertEquals($originalEndDate->format('Y-m-d'), $cancelledSubscription->ends_at->format('Y-m-d'));
        $this->assertArrayHasKey('cancelled_at', $cancelledSubscription->metadata);
    }

    public function test_can_renew_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan, [
            'billing_cycle' => 'monthly',
        ]);
        
        $originalEndDate = $subscription->ends_at;
        
        $renewedSubscription = $this->subscriptionService->renewSubscription($subscription);

        $this->assertEquals($originalEndDate->addMonth()->format('Y-m-d'), $renewedSubscription->ends_at->format('Y-m-d'));
        $this->assertEquals('active', $renewedSubscription->status);
    }

    public function test_can_get_usage_summary()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        
        $summary = $this->subscriptionService->getUsageSummary($subscription);

        $this->assertArrayHasKey('products', $summary);
        $this->assertArrayHasKey('transactions', $summary);
        $this->assertArrayHasKey('users', $summary);
        $this->assertArrayHasKey('outlets', $summary);
        
        $this->assertEquals(0, $summary['products']['current_usage']);
        $this->assertEquals(20, $summary['products']['limit']);
        $this->assertEquals(12000, $summary['transactions']['annual_quota']);
    }

    public function test_can_get_expiring_subscriptions()
    {
        // Create subscription expiring in 5 days
        $expiringSubscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        $expiringSubscription->update(['ends_at' => now()->addDays(5)]);
        
        // Create subscription expiring in 30 days
        $store2 = Store::factory()->create();
        $futureSubscription = $this->subscriptionService->createSubscription($store2, $this->basicPlan);
        $futureSubscription->update(['ends_at' => now()->addDays(30)]);
        
        $expiringSoon = $this->subscriptionService->getExpiringSoon(7);
        
        $this->assertCount(1, $expiringSoon);
        $this->assertEquals($expiringSubscription->id, $expiringSoon->first()->id);
    }

    public function test_check_subscription_status_updates_expired_subscription()
    {
        $subscription = $this->subscriptionService->createSubscription($this->store, $this->basicPlan);
        $subscription->update(['ends_at' => now()->subDay()]);
        
        $checkedSubscription = $this->subscriptionService->checkSubscriptionStatus($subscription);
        
        $this->assertEquals('expired', $checkedSubscription->status);
    }
}