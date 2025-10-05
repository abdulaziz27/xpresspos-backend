<?php

namespace Tests\Unit;

use App\Http\Middleware\PlanGateMiddleware;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PlanGateMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private PlanGateMiddleware $middleware;
    private User $user;
    private Store $store;
    private Plan $basicPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = app(PlanGateMiddleware::class);

        // Create test store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);

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
            'features' => ['pos', 'basic_reports', 'inventory_tracking', 'advanced_reports', 'report_export'],
            'limits' => ['products' => 300, 'users' => 10, 'transactions' => 120000],
        ]);
    }

    public function test_middleware_blocks_request_without_authenticated_user()
    {
        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'pos');

        $this->assertEquals(401, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('UNAUTHENTICATED', $responseData['error']['code']);
    }

    public function test_middleware_blocks_request_without_store_context()
    {
        $userWithoutStore = User::factory()->create(['store_id' => null]);
        $this->actingAs($userWithoutStore);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'pos');

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('NO_STORE_CONTEXT', $responseData['error']['code']);
    }

    public function test_middleware_blocks_request_without_active_subscription()
    {
        $this->actingAs($this->user);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'pos');

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('NO_ACTIVE_SUBSCRIPTION', $responseData['error']['code']);
    }

    public function test_middleware_blocks_request_with_expired_subscription()
    {
        $this->actingAs($this->user);

        // Create expired subscription
        $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'amount' => 99.00,
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'pos');

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('SUBSCRIPTION_EXPIRED', $responseData['error']['code']);
    }

    public function test_middleware_blocks_request_for_unavailable_feature()
    {
        $this->actingAs($this->user);

        // Create active subscription with basic plan
        $this->store->subscriptions()->create([
            'plan_id' => $this->basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 99.00,
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'inventory_tracking');

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('PLAN_FEATURE_REQUIRED', $responseData['error']['code']);
        $this->assertEquals('Pro', $responseData['error']['required_plan']);
    }

    public function test_middleware_blocks_request_when_hard_limit_exceeded()
    {
        $this->actingAs($this->user);

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

        // Create products that exceed the limit
        \App\Models\Product::factory()->count(25)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'products', '20');

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('PLAN_LIMIT_EXCEEDED', $responseData['error']['code']);
    }

    public function test_middleware_allows_request_within_limits()
    {
        $this->actingAs($this->user);

        // Create active subscription with pro plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199.00,
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'inventory_tracking');

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
    }

    public function test_middleware_adds_warning_headers_when_approaching_limits()
    {
        $this->actingAs($this->user);

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

        // Create products approaching the limit (16 out of 20 = 80%)
        \App\Models\Product::factory()->count(16)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'products');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Usage-Warning'));
        $this->assertEquals('Approaching plan limits', $response->headers->get('X-Usage-Warning'));
    }

    public function test_middleware_handles_transaction_quota_soft_cap()
    {
        $this->actingAs($this->user);

        // Create active subscription with basic plan
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

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'transactions');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Quota-Warning'));
        $this->assertEquals('Annual transaction quota exceeded', $response->headers->get('X-Quota-Warning'));
    }

    public function test_middleware_blocks_premium_features_when_quota_exceeded()
    {
        $this->actingAs($this->user);

        // Create active subscription with pro plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199.00,
        ]);

        // Create usage record that exceeds quota
        $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 130000,
            'annual_quota' => 120000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'report_export');

        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('QUOTA_EXCEEDED_PREMIUM_BLOCKED', $responseData['error']['code']);
    }

    public function test_middleware_allows_non_premium_features_when_quota_exceeded()
    {
        $this->actingAs($this->user);

        // Create active subscription with pro plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199.00,
        ]);

        // Create usage record that exceeds quota
        $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 130000,
            'annual_quota' => 120000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);

        $request = Request::create('/test');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, function () {
            return response()->json(['success' => true]);
        }, 'inventory_tracking');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-Quota-Warning'));
    }
}
