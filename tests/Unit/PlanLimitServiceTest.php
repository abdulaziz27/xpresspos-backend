<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Models\Tenant;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PlanLimitService $service;
    protected Tenant $tenant;
    protected Plan $plan;
    protected Subscription $subscription;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlanLimitService::class);

        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'status' => 'active',
        ]);

        // Create plan
        $this->plan = Plan::factory()->create([
            'name' => 'Basic Plan',
            'price' => 99000,
            'is_active' => true,
        ]);

        // Create plan features
        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'MAX_STORES',
            'limit_value' => '3',
            'is_enabled' => true,
        ]);

        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'ALLOW_LOYALTY',
            'limit_value' => '1',
            'is_enabled' => true,
        ]);

        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'MAX_TRANSACTIONS_PER_YEAR',
            'limit_value' => '10000',
            'is_enabled' => true,
        ]);

        // Create subscription
        $this->subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Create store
        $this->store = Store::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Store',
        ]);
    }


    public function test_has_feature_mengembalikan_true_saat_feature_diaktifkan(): void
    {
        // Act
        $hasLoyalty = $this->service->hasFeature($this->tenant, 'ALLOW_LOYALTY');

        // Assert
        $this->assertTrue($hasLoyalty);
    }


    public function test_has_feature_mengembalikan_false_saat_feature_tidak_diaktifkan(): void
    {
        // Arrange: Deactivate current subscription and create plan without loyalty feature
        $this->subscription->update(['status' => 'inactive']);
        
        $planWithoutLoyalty = Plan::factory()->create(['name' => 'No Loyalty Plan']);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $planWithoutLoyalty->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act
        $hasLoyalty = $this->service->hasFeature($this->tenant, 'ALLOW_LOYALTY');

        // Assert: Should return false because current active subscription doesn't have this feature
        $this->assertFalse($hasLoyalty);
    }


    public function test_has_feature_bekerja_dengan_store_entity(): void
    {
        // Arrange: Ensure store has tenant_id
        $this->store->refresh();
        
        // Act: Pass Store instead of Tenant (will resolve via tenant_id)
        $hasLoyalty = $this->service->hasFeature($this->store, 'ALLOW_LOYALTY');

        // Assert: Should resolve to tenant and check feature
        $this->assertTrue($hasLoyalty);
    }


    public function test_limit_mengembalikan_nilai_benar_untuk_limit_numerik(): void
    {
        // Act
        $maxStores = $this->service->limit($this->tenant, 'MAX_STORES');

        // Assert
        $this->assertEquals(3, $maxStores);
    }


    public function test_limit_mengembalikan_minus_satu_untuk_unlimited(): void
    {
        // Arrange: Create plan with unlimited stores
        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'MAX_PRODUCTS',
            'limit_value' => '-1',
            'is_enabled' => true,
        ]);

        // Act
        $maxProducts = $this->service->limit($this->tenant, 'MAX_PRODUCTS');

        // Assert
        $this->assertEquals(-1, $maxProducts);
    }


    public function test_limit_mengembalikan_null_saat_feature_tidak_ditemukan(): void
    {
        // Act
        $limit = $this->service->limit($this->tenant, 'NON_EXISTENT_FEATURE');

        // Assert
        $this->assertNull($limit);
    }


    public function test_is_within_limit_mengembalikan_true_saat_jumlah_saat_ini_dibawah_limit(): void
    {
        // Arrange: Tenant has 1 store, limit is 3
        $currentStores = 1;

        // Act
        $isWithinLimit = $this->service->isWithinLimit($this->tenant, 'MAX_STORES', $currentStores);

        // Assert
        $this->assertTrue($isWithinLimit);
    }


    public function test_is_within_limit_mengembalikan_false_saat_jumlah_saat_ini_melebihi_limit(): void
    {
        // Arrange: Tenant has 4 stores, limit is 3
        $currentStores = 4;

        // Act
        $isWithinLimit = $this->service->isWithinLimit($this->tenant, 'MAX_STORES', $currentStores);

        // Assert
        $this->assertFalse($isWithinLimit);
    }


    public function test_is_within_limit_mengembalikan_true_untuk_unlimited(): void
    {
        // Arrange: Create unlimited feature
        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'MAX_PRODUCTS',
            'limit_value' => '-1',
            'is_enabled' => true,
        ]);

        // Act: Even with high count, should return true for unlimited
        $isWithinLimit = $this->service->isWithinLimit($this->tenant, 'MAX_PRODUCTS', 999999);

        // Assert
        $this->assertTrue($isWithinLimit);
    }


    public function test_track_usage_membuat_record_usage_jika_belum_ada(): void
    {
        // Act
        $usage = $this->service->trackUsage($this->tenant, 'transactions', 1);

        // Assert
        $this->assertNotNull($usage);
        $this->assertInstanceOf(SubscriptionUsage::class, $usage);
        $this->assertEquals(1, $usage->current_usage);
        $this->assertEquals(10000, $usage->annual_quota); // From MAX_TRANSACTIONS_PER_YEAR
    }


    public function test_track_usage_menambah_record_usage_yang_sudah_ada(): void
    {
        // Arrange: Create existing usage
        SubscriptionUsage::factory()->create([
            'subscription_id' => $this->subscription->id,
            'feature_type' => 'transactions',
            'current_usage' => 100,
            'annual_quota' => 10000,
        ]);

        // Act: Track more usage
        $usage = $this->service->trackUsage($this->tenant, 'transactions', 50);

        // Assert
        $this->assertEquals(150, $usage->current_usage);
    }


    public function test_track_usage_memicu_soft_cap_pada_threshold(): void
    {
        // Arrange: Create usage near threshold (80% of 10000 = 8000)
        SubscriptionUsage::factory()->create([
            'subscription_id' => $this->subscription->id,
            'feature_type' => 'transactions',
            'current_usage' => 7999,
            'annual_quota' => 10000,
            'soft_cap_triggered' => false,
        ]);

        // Act: Track usage that crosses threshold
        $usage = $this->service->trackUsage($this->tenant, 'transactions', 1);

        // Assert: Soft cap should be triggered
        $this->assertTrue($usage->soft_cap_triggered);
        $this->assertNotNull($usage->soft_cap_triggered_at);
    }


    public function test_get_usage_mengembalikan_data_usage_yang_benar(): void
    {
        // Arrange: Create usage record
        SubscriptionUsage::factory()->create([
            'subscription_id' => $this->subscription->id,
            'feature_type' => 'transactions',
            'current_usage' => 5000,
            'annual_quota' => 10000,
        ]);

        // Act
        $usage = $this->service->getUsage($this->tenant, 'transactions');

        // Assert
        $this->assertEquals(5000, $usage['current']);
        $this->assertEquals(10000, $usage['quota']);
        $this->assertEquals(50.0, $usage['percentage']);
    }


    public function test_get_usage_mengembalikan_nol_saat_tidak_ada_record_usage(): void
    {
        // Act
        $usage = $this->service->getUsage($this->tenant, 'transactions');

        // Assert
        $this->assertEquals(0, $usage['current']);
        $this->assertNull($usage['quota']);
        $this->assertNull($usage['percentage']);
    }


    public function test_can_perform_action_mengembalikan_allowed_untuk_feature_flag(): void
    {
        // Act
        $result = $this->service->canPerformAction($this->tenant, 'use_loyalty');

        // Assert
        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }


    public function test_can_perform_action_mengembalikan_not_allowed_saat_feature_dinonaktifkan(): void
    {
        // Arrange: Deactivate current subscription and create plan without loyalty
        $this->subscription->update(['status' => 'inactive']);
        
        $planWithoutLoyalty = Plan::factory()->create(['name' => 'No Loyalty Plan']);
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $planWithoutLoyalty->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act
        $result = $this->service->canPerformAction($this->tenant, 'use_loyalty');

        // Assert
        $this->assertFalse($result['allowed']);
        $this->assertEquals('feature_not_available', $result['reason']);
    }


    public function test_can_perform_action_mengembalikan_allowed_saat_dalam_limit(): void
    {
        // Arrange: Current stores = 1, limit = 3
        $currentStores = 1;

        // Act
        $result = $this->service->canPerformAction($this->tenant, 'create_store', $currentStores);

        // Assert
        $this->assertTrue($result['allowed']);
        $this->assertEquals(3, $result['limit']);
    }


    public function test_can_perform_action_mengembalikan_not_allowed_saat_limit_dilampaui(): void
    {
        // Arrange: Current stores = 4, limit = 3
        $currentStores = 4;

        // Act
        $result = $this->service->canPerformAction($this->tenant, 'create_store', $currentStores);

        // Assert
        $this->assertFalse($result['allowed']);
        $this->assertEquals('limit_exceeded', $result['reason']);
        $this->assertStringContainsString('Limit exceeded', $result['message']);
    }


    public function test_can_perform_action_mengembalikan_not_allowed_saat_tidak_ada_subscription(): void
    {
        // Arrange: Create tenant without subscription
        $tenantWithoutSubscription = Tenant::factory()->create(['name' => 'No Subscription Tenant']);

        // Act
        $result = $this->service->canPerformAction($tenantWithoutSubscription, 'create_store', 0);

        // Assert
        $this->assertFalse($result['allowed']);
        $this->assertEquals('no_subscription', $result['reason']);
    }


    public function test_can_perform_action_mengembalikan_not_allowed_saat_subscription_kadaluarsa(): void
    {
        // Arrange: Deactivate current subscription and create expired subscription
        $this->subscription->update(['status' => 'inactive']);
        
        // Create expired subscription (ends_at in the past)
        $expiredSubscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(), // Expired
        ]);

        // Act: Since activeSubscription() filters by ends_at > now(), expired subscription won't be returned
        // So it will be treated as no_subscription
        $result = $this->service->canPerformAction($this->tenant, 'create_store', 0);

        // Assert: Expired subscription is not returned by activeSubscription(), so treated as no_subscription
        $this->assertFalse($result['allowed']);
        $this->assertEquals('no_subscription', $result['reason']);
    }
}

