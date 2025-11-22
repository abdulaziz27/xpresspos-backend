<?php

namespace Tests\Feature;

use App\Models\LandingSubscription;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticatedCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionProvisioningService $provisioningService;
    protected Plan $plan;
    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->provisioningService = app(SubscriptionProvisioningService::class);
        
        // Create plan with features
        $this->plan = Plan::factory()->create([
            'name' => 'Basic Plan',
            'price' => 99000,
            'annual_price' => 990000,
            'is_active' => true,
        ]);
        
        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'MAX_STORES',
            'limit_value' => '3',
            'is_enabled' => true,
        ]);
        
        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'MAX_TRANSACTIONS_PER_YEAR',
            'limit_value' => '10000',
            'is_enabled' => true,
        ]);
        
        PlanFeature::factory()->create([
            'plan_id' => $this->plan->id,
            'feature_code' => 'ALLOW_LOYALTY',
            'limit_value' => '1',
            'is_enabled' => true,
        ]);
        
        // Create owner role
        if (!Role::where('name', 'owner')->where('guard_name', 'web')->exists()) {
            Role::create(['name' => 'owner', 'guard_name' => 'web']);
        }
        
        // Create user and tenant
        $this->user = User::factory()->create([
            'email' => 'owner@example.com',
            'name' => 'Test Owner',
        ]);
        
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'email' => 'tenant@example.com',
        ]);
        
        // Create user_tenant_access
        DB::table('user_tenant_access')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_authenticated_checkout_creates_landing_subscription_dengan_user_dan_tenant(): void
    {
        // Act: Simulate authenticated checkout
        $landingSubscription = LandingSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'payment_amount' => 99000,
            'status' => 'pending',
            'stage' => 'payment_pending',
            'payment_status' => 'pending',
        ]);

        // Assert: Landing subscription terisi dengan benar
        $this->assertDatabaseHas('landing_subscriptions', [
            'id' => $landingSubscription->id,
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'payment_amount' => 99000,
            'status' => 'pending',
            'stage' => 'payment_pending',
        ]);

        // Assert: Relasi berfungsi
        $this->assertEquals($this->user->id, $landingSubscription->user->id);
        $this->assertEquals($this->tenant->id, $landingSubscription->tenant->id);
        $this->assertEquals($this->plan->id, $landingSubscription->plan->id);
    }

    public function test_authenticated_checkout_end_to_end_dari_checkout_hingga_provisioning(): void
    {
        // Step 1: Create landing subscription (simulate checkout)
        $landingSubscription = LandingSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'payment_amount' => 99000,
            'status' => 'pending',
            'stage' => 'payment_pending',
            'payment_status' => 'pending',
        ]);

        // Step 2: Create subscription payment (simulate Xendit webhook)
        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 99000,
            'xendit_invoice_id' => 'xendit_inv_' . uniqid(),
            'external_id' => 'ext_' . uniqid(),
        ]);

        // Step 3: Trigger provisioning (simulate webhook handler)
        $result = $this->provisioningService->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Success
        $this->assertTrue($result['success']);

        // Assert: Menggunakan tenant & user yang sudah ada (tidak membuat baru)
        $this->assertEquals($this->tenant->id, $result['tenant']->id);
        $this->assertEquals($this->user->id, $result['user']->id);

        // Assert: Subscription dibuat untuk tenant yang sudah ada
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        // Assert: SubscriptionUsage dibuat dari plan_features
        $subscription = $result['subscription'];
        $this->assertDatabaseHas('subscription_usage', [
            'subscription_id' => $subscription->id,
            'feature_type' => 'transactions',
        ]);

        // Assert: landing_subscription terupdate
        $landingSubscription->refresh();
        $this->assertNotNull($landingSubscription->subscription_id);
        $this->assertEquals($subscription->id, $landingSubscription->subscription_id);
        $this->assertEquals('provisioned', $landingSubscription->status);
    }

    public function test_authenticated_checkout_tidak_membuat_tenant_baru(): void
    {
        // Arrange: Tenant sudah ada
        $initialTenantCount = Tenant::count();

        // Act: Checkout dan provisioning
        $landingSubscription = LandingSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'payment_amount' => 99000,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $result = $this->provisioningService->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Tidak ada tenant baru yang dibuat
        $this->assertEquals($initialTenantCount, Tenant::count());
        $this->assertEquals($this->tenant->id, $result['tenant']->id);
    }

    public function test_authenticated_checkout_tidak_membuat_user_baru(): void
    {
        // Arrange: User sudah ada
        $initialUserCount = User::count();

        // Act: Checkout dan provisioning
        $landingSubscription = LandingSubscription::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'payment_amount' => 99000,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $result = $this->provisioningService->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Tidak ada user baru yang dibuat
        $this->assertEquals($initialUserCount, User::count());
        $this->assertEquals($this->user->id, $result['user']->id);
    }
}

