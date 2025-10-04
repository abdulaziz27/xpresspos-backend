<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Services\UpgradeRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    private UpgradeRecommendationService $service;
    private Store $store;
    private Plan $basicPlan;
    private Plan $proPlan;
    private Plan $enterprisePlan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new UpgradeRecommendationService();
        
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
            'features' => ['pos', 'basic_reports', 'inventory_tracking', 'advanced_reports'],
            'limits' => ['products' => 300, 'users' => 10, 'transactions' => 120000],
        ]);
        
        $this->enterprisePlan = Plan::factory()->create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price' => 399.00,
            'annual_price' => 3990.00,
            'features' => ['pos', 'basic_reports', 'inventory_tracking', 'advanced_reports', 'multi_outlet'],
            'limits' => ['products' => null, 'users' => null, 'transactions' => null],
        ]);
    }

    public function test_no_recommendation_without_subscription()
    {
        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertFalse($recommendation['recommended']);
        $this->assertEquals('no_subscription', $recommendation['reason']);
    }

    public function test_no_recommendation_when_within_limits()
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
        
        // Create minimal usage
        \App\Models\Product::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertFalse($recommendation['recommended']);
        $this->assertEquals('within_limits', $recommendation['reason']);
    }

    public function test_high_urgency_recommendation_when_limits_exceeded()
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

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertTrue($recommendation['recommended']);
        $this->assertEquals('limits_exceeded', $recommendation['reason']);
        $this->assertEquals('high', $recommendation['urgency']);
        $this->assertEquals('Pro', $recommendation['recommended_plan']['name']);
    }

    public function test_medium_urgency_recommendation_when_approaching_limits()
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

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertTrue($recommendation['recommended']);
        $this->assertEquals('approaching_limits', $recommendation['reason']);
        $this->assertEquals('medium', $recommendation['urgency']);
    }

    public function test_recommendation_includes_upgrade_benefits()
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

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertArrayHasKey('benefits', $recommendation);
        $this->assertNotEmpty($recommendation['benefits']);
        
        // Check for specific benefits
        $benefitTypes = collect($recommendation['benefits'])->pluck('type')->toArray();
        $this->assertContains('increased_limit', $benefitTypes);
        $this->assertContains('new_feature', $benefitTypes);
    }

    public function test_recommendation_includes_cost_analysis()
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

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertArrayHasKey('estimated_savings', $recommendation);
        $this->assertArrayHasKey('monthly_cost_increase', $recommendation['estimated_savings']);
        $this->assertArrayHasKey('annual_cost_increase', $recommendation['estimated_savings']);
        $this->assertArrayHasKey('annual_billing_savings', $recommendation['estimated_savings']);
        
        // Basic to Pro upgrade should cost $100 more per month
        $this->assertEquals(100.00, $recommendation['estimated_savings']['monthly_cost_increase']);
    }

    public function test_transaction_quota_analysis()
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

        // Create usage record exceeding quota
        $usage = $subscription->usage()->create([
            'feature_type' => 'transactions',
            'current_usage' => 13000,
            'annual_quota' => 12000,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
            'soft_cap_triggered' => true,
        ]);

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertTrue($recommendation['recommended']);
        $this->assertEquals('high', $recommendation['urgency']);
        
        $this->assertArrayHasKey('transaction_quota', $recommendation['usage_analysis']);
        $this->assertTrue($recommendation['usage_analysis']['transaction_quota']['quota_exceeded']);
        $this->assertTrue($recommendation['usage_analysis']['transaction_quota']['soft_cap_triggered']);
    }

    public function test_pro_to_enterprise_upgrade_path()
    {
        // Create active subscription with pro plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products exceeding pro limit
        \App\Models\Product::factory()->count(350)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertTrue($recommendation['recommended']);
        $this->assertEquals('Enterprise', $recommendation['recommended_plan']['name']);
        $this->assertEquals('Pro', $recommendation['current_plan']['name']);
    }

    public function test_unlimited_features_in_benefits()
    {
        // Create active subscription with pro plan
        $subscription = $this->store->subscriptions()->create([
            'plan_id' => $this->proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => 199.00,
        ]);

        // Create a category first
        $category = $this->store->categories()->create([
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
        
        // Create products exceeding pro limit
        \App\Models\Product::factory()->count(350)->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        // Check for unlimited benefits
        $unlimitedBenefits = collect($recommendation['benefits'])
            ->where('type', 'unlimited')
            ->toArray();
        
        $this->assertNotEmpty($unlimitedBenefits);
        
        $unlimitedFeatures = collect($unlimitedBenefits)->pluck('feature')->toArray();
        $this->assertContains('products', $unlimitedFeatures);
    }

    public function test_usage_analysis_includes_all_features()
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

        $recommendation = $this->service->getUpgradeRecommendation($this->store);

        $this->assertArrayHasKey('usage_analysis', $recommendation);
        $this->assertArrayHasKey('features', $recommendation['usage_analysis']);
        
        $features = $recommendation['usage_analysis']['features'];
        $this->assertArrayHasKey('products', $features);
        $this->assertArrayHasKey('users', $features);
        $this->assertArrayHasKey('outlets', $features);
        $this->assertArrayHasKey('transactions', $features);
        
        // Each feature should have required fields
        foreach ($features as $feature => $analysis) {
            $this->assertArrayHasKey('current_usage', $analysis);
            $this->assertArrayHasKey('limit', $analysis);
            $this->assertArrayHasKey('usage_percentage', $analysis);
            $this->assertArrayHasKey('status', $analysis);
        }
    }
}