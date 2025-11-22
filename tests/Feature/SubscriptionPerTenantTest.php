<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPerTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Plan $plan;
    protected Store $store1;
    protected Store $store2;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Create stores for tenant
        $this->store1 = Store::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store 1',
        ]);

        $this->store2 = Store::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store 2',
        ]);
    }

    public function test_subscription_milik_tenant_bukan_store(): void
    {
        // Arrange: Create subscription for tenant
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act: Get tenant from subscription
        $tenant = $subscription->tenant;

        // Assert: Subscription belongs to tenant
        $this->assertNotNull($tenant);
        $this->assertEquals($this->tenant->id, $tenant->id);
        $this->assertEquals($this->tenant->name, $tenant->name);
    }

    public function test_tenant_bisa_memiliki_beberapa_subscription(): void
    {
        // Arrange: Create multiple subscriptions for same tenant
        $subscription1 = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'inactive',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        $subscription2 = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act: Get all subscriptions for tenant
        $subscriptions = $this->tenant->subscriptions;

        // Assert: Tenant has multiple subscriptions
        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->contains($subscription1));
        $this->assertTrue($subscriptions->contains($subscription2));
    }

    public function test_tenant_active_subscription_mengembalikan_hanya_yang_aktif_dan_tidak_kadaluarsa(): void
    {
        // Arrange: Create multiple subscriptions
        $expiredSubscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(), // Expired
        ]);

        $inactiveSubscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'inactive',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $activeSubscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act: Get active subscription
        $active = $this->tenant->activeSubscription();

        // Assert: Only active and not expired subscription is returned
        $this->assertNotNull($active);
        $this->assertEquals($activeSubscription->id, $active->id);
        $this->assertEquals('active', $active->status);
        $this->assertTrue($active->ends_at->isFuture());
    }

    public function test_tenant_active_subscription_mengembalikan_null_saat_tidak_ada_subscription_aktif(): void
    {
        // Arrange: Create only expired/inactive subscriptions
        Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'expired',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        // Act: Get active subscription
        $active = $this->tenant->activeSubscription();

        // Assert: No active subscription found
        $this->assertNull($active);
    }

    public function test_semua_store_di_tenant_berbagi_subscription_yang_sama(): void
    {
        // Arrange: Create subscription for tenant
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act: Get subscription via tenant (not via store)
        $tenantSubscription = $this->tenant->activeSubscription();
        
        // Get tenant from stores (via tenant_id)
        $store1Tenant = Tenant::find($this->store1->tenant_id);
        $store2Tenant = Tenant::find($this->store2->tenant_id);

        // Assert: All stores share the same subscription through tenant
        $this->assertNotNull($tenantSubscription);
        $this->assertEquals($subscription->id, $tenantSubscription->id);
        $this->assertNotNull($store1Tenant);
        $this->assertNotNull($store2Tenant);
        $this->assertEquals($this->tenant->id, $store1Tenant->id);
        $this->assertEquals($this->tenant->id, $store2Tenant->id);
        $this->assertEquals($store1Tenant->activeSubscription()->id, $store2Tenant->activeSubscription()->id);
    }

    public function test_subscription_terhapus_cascade_saat_tenant_dihapus(): void
    {
        // Arrange: Create subscription for tenant
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        $subscriptionId = $subscription->id;

        // Act: Delete tenant
        $this->tenant->delete();

        // Assert: Subscription is also deleted (cascade)
        $this->assertDatabaseMissing('subscriptions', [
            'id' => $subscriptionId,
        ]);
    }

    public function test_subscription_memiliki_schema_database_yang_benar(): void
    {
        // Arrange: Create subscription
        $subscription = Subscription::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
        ]);

        // Act: Check database schema
        $columns = \Schema::getColumnListing('subscriptions');

        // Assert: Schema has tenant_id and does not have store_id
        $this->assertContains('tenant_id', $columns);
        $this->assertNotContains('store_id', $columns);
        $this->assertContains('plan_id', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('billing_cycle', $columns);
    }
}

