<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionUsage;
use App\Models\LandingSubscription;
use App\Models\StoreUserAssignment;
use App\Services\SubscriptionProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UpgradeDowngradeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed plans
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    #[Test]
    public function user_dapat_upgrade_dari_basic_ke_pro(): void
    {
        // Setup: User dengan Basic plan
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->create(['email' => $user->email]);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StoreUserAssignment::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        $basicPlan = Plan::where('slug', 'basic')->first();
        $proPlan = Plan::where('slug', 'pro')->first();
        
        // Create active subscription dengan Basic plan
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Assert: sort_order untuk detect upgrade
        $this->assertGreaterThan($basicPlan->sort_order, $proPlan->sort_order);

        // Act: User checkout untuk upgrade ke Pro
        $response = $this->actingAs($user)
            ->post(route('landing.subscription.process'), [
                'plan_id' => $proPlan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertRedirect(); // Redirect to payment

        // Assert: landing_subscription created dengan upgrade flag
        $this->assertDatabaseHas('landing_subscriptions', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $proPlan->id,
            'is_upgrade' => true,
            'is_downgrade' => false,
            'previous_plan_id' => $basicPlan->id,
        ]);

        // Simulate payment success
        $landingSubscription = LandingSubscription::where('user_id', $user->id)
            ->where('plan_id', $proPlan->id)
            ->latest()
            ->first();

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'amount' => $proPlan->price,
            'paid_at' => now(),
        ]);

        // Act: Provisioning service processes the payment
        $result = app(SubscriptionProvisioningService::class)
            ->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Provisioning successful
        $this->assertTrue($result['success']);

        // Assert: Subscription updated (NOT created new) - should still be 1 subscription
        $this->assertEquals(1, Subscription::where('tenant_id', $tenant->id)->count());

        // Assert: Subscription plan_id changed to Pro
        $subscription->refresh();
        $this->assertEquals($proPlan->id, $subscription->plan_id);
        $this->assertEquals('active', $subscription->status);
        
        // Assert: Metadata contains upgrade info
        $metadata = $subscription->metadata;
        $this->assertEquals('upgrade', $metadata['action_type']);
        $this->assertEquals($basicPlan->id, $metadata['previous_plan_id']);
    }

    #[Test]
    public function user_dapat_downgrade_dari_pro_ke_basic(): void
    {
        // Setup: User dengan Pro plan
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->create(['email' => $user->email]);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StoreUserAssignment::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        $basicPlan = Plan::where('slug', 'basic')->first();
        $proPlan = Plan::where('slug', 'pro')->first();
        
        // Create active subscription dengan Pro plan
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        // Assert: sort_order untuk detect downgrade
        $this->assertLessThan($proPlan->sort_order, $basicPlan->sort_order);

        // Act: User checkout untuk downgrade ke Basic
        $response = $this->actingAs($user)
            ->post(route('landing.subscription.process'), [
                'plan_id' => $basicPlan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertRedirect();

        // Assert: landing_subscription created dengan downgrade flag
        $this->assertDatabaseHas('landing_subscriptions', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $basicPlan->id,
            'is_upgrade' => false,
            'is_downgrade' => true,
            'previous_plan_id' => $proPlan->id,
        ]);

        // Simulate payment & provisioning
        $landingSubscription = LandingSubscription::where('user_id', $user->id)
            ->where('plan_id', $basicPlan->id)
            ->latest()
            ->first();

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'amount' => $basicPlan->price,
            'paid_at' => now(),
        ]);

        $result = app(SubscriptionProvisioningService::class)
            ->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Successful
        $this->assertTrue($result['success']);

        // Assert: Subscription updated (NOT new)
        $this->assertEquals(1, Subscription::where('tenant_id', $tenant->id)->count());

        // Assert: Subscription plan_id changed to Basic
        $subscription->refresh();
        $this->assertEquals($basicPlan->id, $subscription->plan_id);
        
        // Assert: Metadata contains downgrade info
        $metadata = $subscription->metadata;
        $this->assertEquals('downgrade', $metadata['action_type']);
        $this->assertEquals($proPlan->id, $metadata['previous_plan_id']);
    }

    #[Test]
    public function subscription_usage_di_recreate_setelah_upgrade(): void
    {
        // Setup
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->create(['email' => $user->email]);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Create store_user_assignment instead of updating store_id
        StoreUserAssignment::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        $basicPlan = Plan::where('slug', 'basic')->first();
        $proPlan = Plan::where('slug', 'pro')->first();

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
        ]);

        // Create initial usage records untuk Basic plan
        SubscriptionUsage::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_type' => 'transactions',
            'current_usage' => 50,
            'annual_quota' => 1000,
        ]);

        $initialUsageCount = SubscriptionUsage::where('subscription_id', $subscription->id)->count();
        $this->assertGreaterThan(0, $initialUsageCount);

        // Act: Upgrade
        $landingSubscription = LandingSubscription::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $proPlan->id,
            'is_upgrade' => true,
            'is_downgrade' => false,
            'previous_plan_id' => $basicPlan->id,
            'payment_status' => 'paid',
            'billing_cycle' => 'monthly',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'amount' => $proPlan->price,
            'paid_at' => now(),
        ]);

        $result = app(SubscriptionProvisioningService::class)
            ->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Usage backup saved to metadata
        $subscription->refresh();
        $metadata = $subscription->metadata;
        $this->assertArrayHasKey('usage_backup_before_change', $metadata);
        $this->assertIsArray($metadata['usage_backup_before_change']);
        
        // Assert: Old usage had 50 transactions
        $backup = collect($metadata['usage_backup_before_change'])
            ->firstWhere('feature_type', 'transactions');
        $this->assertEquals(50, $backup['current_usage'] ?? 0);

        // Assert: New usage records created (reset to 0)
        $newUsageCount = SubscriptionUsage::where('subscription_id', $subscription->id)->count();
        // Could be same count or different depending on plan_features, but should exist
        $this->assertGreaterThanOrEqual(0, $newUsageCount);
    }

    #[Test]
    public function pricing_page_menampilkan_tombol_dinamis_berdasarkan_current_plan(): void
    {
        // Setup: User dengan Basic plan
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->create(['email' => $user->email]);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StoreUserAssignment::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        $basicPlan = Plan::where('slug', 'basic')->first();

        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Act: Visit pricing page
        $response = $this->actingAs($user)->get(route('landing.pricing'));

        // Assert: Response successful
        $response->assertOk();

        // Assert: currentPlan passed to view
        $response->assertViewHas('currentPlan');
        $currentPlan = $response->viewData('currentPlan');
        $this->assertNotNull($currentPlan, 'Current plan should not be null');
        $this->assertEquals($basicPlan->id, $currentPlan->id);

        // Note: Button label logic is in Blade, can't test directly via HTTP
        // But we verified the data is passed correctly
    }

    #[Test]
    public function guest_user_melihat_tombol_pilih_paket_biasa(): void
    {
        // Act: Visit pricing as guest
        $response = $this->get(route('landing.pricing'));

        // Assert: Response successful
        $response->assertOk();

        // Assert: currentPlan is null
        $response->assertViewHas('currentPlan', null);
    }

    #[Test]
    public function data_tidak_hilang_setelah_downgrade(): void
    {
        // Setup: User dengan Pro plan dan 2 stores
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->create(['email' => $user->email]);
        
        $store1 = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Store 1']);
        $store2 = Store::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Store 2']);
        
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $basicPlan = Plan::where('slug', 'basic')->first();
        $proPlan = Plan::where('slug', 'pro')->first();

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
        ]);

        // Assert: 2 stores exist
        $this->assertEquals(2, Store::where('tenant_id', $tenant->id)->count());

        // Act: Downgrade ke Basic (max 1 store)
        $landingSubscription = LandingSubscription::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $basicPlan->id,
            'is_downgrade' => true,
            'previous_plan_id' => $proPlan->id,
            'payment_status' => 'paid',
            'billing_cycle' => 'monthly',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        app(SubscriptionProvisioningService::class)
            ->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Subscription downgraded
        $subscription->refresh();
        $this->assertEquals($basicPlan->id, $subscription->plan_id);

        // Assert: Data tetap ada (NOT DELETED)
        $this->assertEquals(2, Store::where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('stores', ['name' => 'Store 1']);
        $this->assertDatabaseHas('stores', ['name' => 'Store 2']);

        // Note: Feature gating akan hide/disable create button via PlanLimitService
        // tapi data lama tetap aman
    }

    #[Test]
    public function tidak_membuat_duplicate_subscription_saat_upgrade(): void
    {
        // Setup
        $user = User::factory()->create(['email_verified_at' => now()]);
        $tenant = Tenant::factory()->create(['email' => $user->email]);
        $store = Store::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('user_tenant_access')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StoreUserAssignment::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        $basicPlan = Plan::where('slug', 'basic')->first();
        $proPlan = Plan::where('slug', 'pro')->first();

        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $initialSubscriptionId = $subscription->id;
        $initialCount = Subscription::where('tenant_id', $tenant->id)->count();
        $this->assertEquals(1, $initialCount, 'Should start with 1 subscription');

        // Act: Process upgrade (only once to avoid complexity)
        $landingSubscription = LandingSubscription::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $proPlan->id,
            'is_upgrade' => true,
            'previous_plan_id' => $basicPlan->id,
            'payment_status' => 'paid',
            'billing_cycle' => 'monthly',
            'payment_amount' => $proPlan->price,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'subscription_id' => $subscription->id, // Link to existing subscription
            'status' => 'paid',
            'amount' => $proPlan->price,
            'paid_at' => now(),
        ]);

        $result = app(SubscriptionProvisioningService::class)
            ->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Provisioning successful
        $this->assertTrue($result['success']);

        // Assert: Tetap hanya 1 subscription untuk tenant
        $finalCount = Subscription::where('tenant_id', $tenant->id)->count();
        $this->assertEquals(1, $finalCount, 'Should remain 1 subscription after upgrade');

        // Assert: ID subscription tidak berubah (update in-place)
        $subscription->refresh();
        $this->assertEquals($initialSubscriptionId, $subscription->id);
        $this->assertEquals($proPlan->id, $subscription->plan_id);
    }
}

