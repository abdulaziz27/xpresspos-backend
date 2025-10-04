<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Services\PlanLimitValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderObserverTest extends TestCase
{
    use RefreshDatabase;

    private OrderObserver $observer;
    private Store $store;
    private User $user;
    private Plan $basicPlan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->observer = new OrderObserver();
        
        // Create test store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        
        // Create test plan
        $this->basicPlan = Plan::factory()->create([
            'name' => 'Basic',
            'slug' => 'basic',
            'features' => ['pos', 'basic_reports', 'transactions'],
            'limits' => ['products' => 20, 'users' => 2, 'transactions' => 12000],
        ]);
    }

    public function test_observer_increments_transaction_usage_when_order_completed()
    {
        // Create active subscription
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

        // Create order
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'total_amount' => 100.00,
        ]);

        $this->assertEquals(5000, $usage->current_usage);

        // Simulate order completion
        $order->update(['status' => 'completed']);

        // Check that usage was incremented
        $usage->refresh();
        $this->assertEquals(5001, $usage->current_usage);
    }

    public function test_observer_handles_order_without_subscription()
    {
        // Create order without subscription
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'total_amount' => 100.00,
        ]);

        // This should not throw an exception
        $order->update(['status' => 'completed']);

        // No usage record should be created
        $this->assertEquals(0, $this->store->subscriptions()->count());
    }

    public function test_observer_handles_order_with_expired_subscription()
    {
        // Create expired subscription
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
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

        // Create order
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'total_amount' => 100.00,
        ]);

        $initialUsage = $usage->current_usage;

        // Complete order - should still increment usage even if subscription expired
        $order->update(['status' => 'completed']);

        // Usage should still be incremented (soft cap behavior)
        $usage->refresh();
        $this->assertEquals($initialUsage + 1, $usage->current_usage);
    }

    public function test_observer_triggers_soft_cap_when_quota_exceeded()
    {
        // Create active subscription
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
            'current_usage' => 11999,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        // Create order
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'total_amount' => 100.00,
        ]);

        $this->assertFalse((bool) $usage->soft_cap_triggered);

        // Complete order - should trigger soft cap
        $order->update(['status' => 'completed']);

        // Check that soft cap was triggered
        $usage->refresh();
        $this->assertEquals(12000, $usage->current_usage);
        $this->assertTrue($usage->soft_cap_triggered);
        $this->assertNotNull($usage->soft_cap_triggered_at);
    }

    public function test_observer_only_increments_on_status_change_to_completed()
    {
        // Create active subscription
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

        // Create completed order
        $order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'completed',
            'total_amount' => 100.00,
        ]);

        $initialUsage = $usage->current_usage;

        // Update other fields - should not increment usage
        $order->update(['total_amount' => 150.00]);

        $usage->refresh();
        $this->assertEquals($initialUsage, $usage->current_usage);

        // Update status to same value - should not increment usage
        $order->update(['status' => 'completed']);

        $usage->refresh();
        $this->assertEquals($initialUsage, $usage->current_usage);
    }

    public function test_observer_handles_multiple_order_completions()
    {
        // Create active subscription
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

        // Create multiple orders
        $orders = Order::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'status' => 'open',
            'total_amount' => 100.00,
        ]);

        $initialUsage = $usage->current_usage;

        // Complete all orders
        foreach ($orders as $order) {
            $order->update(['status' => 'completed']);
        }

        // Usage should be incremented by 5
        $usage->refresh();
        $this->assertEquals($initialUsage + 5, $usage->current_usage);
    }
}