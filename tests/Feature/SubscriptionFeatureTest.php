<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test plans
        Plan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'price' => 199000,
            'annual_price' => 1990000,
            'features' => json_encode(['basic_reports', 'inventory_management']),
            'limits' => json_encode([
                'stores' => 1,
                'products' => 500,
                'staff' => 10,
            ]),
            'is_active' => true,
        ]);

        Plan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 499000,
            'annual_price' => 4990000,
            'features' => json_encode([
                'basic_reports',
                'inventory_management',
                'advanced_analytics',
                'multi_store',
                'api_access',
            ]),
            'limits' => json_encode([
                'stores' => 3,
                'products' => 2000,
                'staff' => 50,
            ]),
            'is_active' => true,
        ]);
    }

    /** @test */
    public function free_user_has_no_features()
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasFeature('advanced_analytics'));
        $this->assertFalse($user->hasFeature('multi_store'));
        $this->assertTrue($user->isFreePlan());
        $this->assertEquals('Free', $user->getSubscriptionTier());
    }

    /** @test */
    public function free_user_has_limited_resources()
    {
        $user = User::factory()->create();

        $this->assertEquals(50, $user->getLimit('products'));
        $this->assertEquals(2, $user->getLimit('staff'));
        $this->assertEquals(1, $user->getLimit('stores'));
    }

    /** @test */
    public function basic_user_has_basic_features()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'basic')->first();
        
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $user = $user->fresh();

        $this->assertTrue($user->hasFeature('basic_reports'));
        $this->assertTrue($user->hasFeature('inventory_management'));
        $this->assertFalse($user->hasFeature('advanced_analytics'));
        $this->assertFalse($user->isFreePlan());
        $this->assertEquals('Basic', $user->getSubscriptionTier());
    }

    /** @test */
    public function basic_user_has_increased_limits()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'basic')->first();
        
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $user = $user->fresh();

        $this->assertEquals(500, $user->getLimit('products'));
        $this->assertEquals(10, $user->getLimit('staff'));
        $this->assertEquals(1, $user->getLimit('stores'));
    }

    /** @test */
    public function pro_user_has_all_pro_features()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'pro')->first();
        
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $user = $user->fresh();

        $this->assertTrue($user->hasFeature('basic_reports'));
        $this->assertTrue($user->hasFeature('advanced_analytics'));
        $this->assertTrue($user->hasFeature('multi_store'));
        $this->assertTrue($user->hasFeature('api_access'));
        $this->assertEquals('Pro', $user->getSubscriptionTier());
    }

    /** @test */
    public function pro_user_has_higher_limits()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'pro')->first();
        
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $user = $user->fresh();

        $this->assertEquals(2000, $user->getLimit('products'));
        $this->assertEquals(50, $user->getLimit('staff'));
        $this->assertEquals(3, $user->getLimit('stores'));
    }

    /** @test */
    public function expired_subscription_reverts_to_free()
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'pro')->first();
        
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(), // Expired yesterday
        ]);

        $user = $user->fresh();

        $this->assertFalse($user->hasFeature('advanced_analytics'));
        $this->assertTrue($user->isFreePlan());
        $this->assertEquals(50, $user->getLimit('products')); // Back to free limits
    }

    /** @test */
    public function can_check_if_within_limit()
    {
        $user = User::factory()->create();

        // Free user with 50 product limit
        $this->assertTrue($user->isWithinLimit('products', 30)); // 30 < 50
        $this->assertFalse($user->isWithinLimit('products', 50)); // 50 >= 50
        $this->assertFalse($user->isWithinLimit('products', 60)); // 60 > 50
    }

    /** @test */
    public function usage_percentage_calculated_correctly()
    {
        $user = User::factory()->create();

        // Mock getCurrentCount method
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['getCurrentCount'])
            ->getMock();
        
        $user->method('getCurrentCount')->willReturn(25);
        
        // Free plan has 50 product limit
        // 25/50 = 50%
        $this->assertEquals(50, $user->getUsagePercentage('products'));
    }
}
