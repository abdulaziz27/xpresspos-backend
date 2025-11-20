<?php

namespace Tests\Feature;

use App\Models\LandingSubscription;
use App\Models\SubscriptionPayment;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionUsage;
use App\Models\StoreUserAssignment;
use App\Services\SubscriptionProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionProvisioningService $service;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionProvisioningService::class);

        // Create plan dengan features
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

        // Create 'owner' role if not exists
        if (!Role::where('name', 'owner')->where('guard_name', 'web')->exists()) {
            Role::create(['name' => 'owner', 'guard_name' => 'web']);
        }
    }

    public function test_paid_landing_subscription_membuat_tenant_store_user_subscription(): void
    {
        // Arrange: Create landing subscription (anonymous flow - legacy)
        $landingSubscription = LandingSubscription::factory()->anonymous()->create([
            'email' => 'owner@example.com',
            'name' => 'John Doe',
            'company' => 'Test Company',
            'phone' => '081234567890',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'pending_payment',
            'business_name' => 'Test Business',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 99000,
            'xendit_invoice_id' => 'xendit_inv_' . uniqid(),
            'external_id' => 'ext_' . uniqid(),
        ]);

        // Act: Provision dari paid landing subscription
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Semua entity terbuat
        if (!$result['success']) {
            $this->fail('Provisioning failed: ' . ($result['error'] ?? 'Unknown error'));
        }
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['tenant']);
        $this->assertNotNull($result['user']);
        $this->assertNotNull($result['store']);
        $this->assertNotNull($result['subscription']);

        // Assert: Tenant terbuat
        $this->assertDatabaseHas('tenants', [
            'id' => $result['tenant']->id,
            'name' => 'Test Business',
            'email' => 'owner@example.com',
            'status' => 'active',
        ]);

        // Assert: User terbuat
        $this->assertDatabaseHas('users', [
            'id' => $result['user']->id,
            'email' => 'owner@example.com',
            'name' => 'John Doe',
        ]);

        // Assert: user_tenant_access terbuat (role owner)
        $this->assertDatabaseHas('user_tenant_access', [
            'user_id' => $result['user']->id,
            'tenant_id' => $result['tenant']->id,
            'role' => 'owner',
        ]);

        // Assert: Store terbuat dengan tenant_id
        $this->assertDatabaseHas('stores', [
            'id' => $result['store']->id,
            'tenant_id' => $result['tenant']->id,
            'name' => 'Test Business',
            'status' => 'active',
        ]);

        // Assert: store_user_assignments terbuat (owner, is_primary)
        $this->assertDatabaseHas('store_user_assignments', [
            'store_id' => $result['store']->id,
            'user_id' => $result['user']->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        // Assert: Subscription terbuat dengan tenant_id (bukan store_id)
        $this->assertDatabaseHas('subscriptions', [
            'id' => $result['subscription']->id,
            'tenant_id' => $result['tenant']->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        // Assert: Subscription TIDAK punya store_id
        $subscription = Subscription::find($result['subscription']->id);
        $this->assertNull($subscription->getAttribute('store_id') ?? null);

        // Assert: landing_subscriptions terupdate
        $landingSubscription->refresh();
        $this->assertEquals($result['subscription']->id, $landingSubscription->subscription_id);
        $this->assertEquals($result['user']->id, $landingSubscription->provisioned_user_id);
        $this->assertEquals($result['store']->id, $landingSubscription->provisioned_store_id);
        $this->assertEquals('provisioned', $landingSubscription->status);

        // Assert: subscription_payments terupdate
        $payment->refresh();
        $this->assertEquals($result['subscription']->id, $payment->subscription_id);
    }

    public function test_provisioning_idempotent_tidak_duplikasi_entity(): void
    {
        // Arrange: Create landing subscription dan payment
        $landingSubscription = LandingSubscription::factory()->create([
            'email' => 'owner@example.com',
            'name' => 'John Doe',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 99000,
        ]);

        // Act: Provision pertama kali
        $result1 = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);
        $this->assertTrue($result1['success']);

        $tenantId = $result1['tenant']->id;
        $userId = $result1['user']->id;
        $storeId = $result1['store']->id;
        $subscriptionId = $result1['subscription']->id;

        // Act: Provision kedua kali (harus idempotent)
        $result2 = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);
        $this->assertTrue($result2['success']);

        // Assert: Entity tidak duplikat
        $this->assertEquals($tenantId, $result2['tenant']->id);
        $this->assertEquals($userId, $result2['user']->id);
        $this->assertEquals($storeId, $result2['store']->id);
        $this->assertEquals($subscriptionId, $result2['subscription']->id);

        // Assert: Jumlah entity tetap sama
        $this->assertEquals(1, Tenant::count());
        $this->assertEquals(1, User::where('email', 'owner@example.com')->count());
        $this->assertEquals(1, Store::where('tenant_id', $tenantId)->count());
        $this->assertEquals(1, Subscription::where('tenant_id', $tenantId)->count());
    }

    public function test_provisioning_membuat_subscription_usage_dari_plan_features(): void
    {
        // Arrange
        $landingSubscription = LandingSubscription::factory()->create([
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: SubscriptionUsage terbuat untuk MAX_TRANSACTIONS_PER_YEAR
        $usage = SubscriptionUsage::where('subscription_id', $result['subscription']->id)
            ->where('feature_type', 'transactions')
            ->first();

        $this->assertNotNull($usage);
        $this->assertEquals(0, $usage->current_usage);
        $this->assertEquals(10000, $usage->annual_quota);
        $this->assertFalse($usage->soft_cap_triggered);
    }

    public function test_provisioning_gagal_jika_payment_belum_paid(): void
    {
        // Arrange
        $landingSubscription = LandingSubscription::factory()->create([
            'plan_id' => $this->plan->id,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'pending', // Belum paid
            'paid_at' => null,
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Gagal
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not paid', $result['error']);
    }

    public function test_provisioning_gagal_jika_plan_tidak_ditemukan(): void
    {
        // Arrange
        $landingSubscription = LandingSubscription::factory()->create([
            'plan_id' => null, // Plan tidak ada (null atau invalid)
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Gagal
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Plan not found', $result['error']);
    }

    public function test_provisioning_existing_user_menggunakan_tenant_yang_sudah_ada(): void
    {
        // Arrange: Buat user dan tenant yang sudah ada
        $existingTenant = Tenant::factory()->create([
            'name' => 'Existing Tenant',
            'email' => 'owner@example.com',
        ]);

        $existingUser = User::factory()->create([
            'email' => 'owner@example.com',
            'name' => 'Existing User',
        ]);

        // Buat user_tenant_access
        DB::table('user_tenant_access')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $existingUser->id,
            'tenant_id' => $existingTenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create landing subscription dengan email yang sama
        $landingSubscription = LandingSubscription::factory()->create([
            'email' => 'owner@example.com',
            'name' => 'Existing User',
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Menggunakan tenant yang sudah ada
        $this->assertTrue($result['success']);
        $this->assertEquals($existingTenant->id, $result['tenant']->id);
        $this->assertEquals($existingUser->id, $result['user']->id);

        // Assert: Store baru terbuat untuk tenant yang sama
        $this->assertDatabaseHas('stores', [
            'tenant_id' => $existingTenant->id,
        ]);

        // Assert: Subscription baru terbuat untuk tenant yang sama
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $existingTenant->id,
            'plan_id' => $this->plan->id,
        ]);
    }

    public function test_provisioning_mengirim_email_welcome_untuk_user_baru(): void
    {
        // Arrange
        Mail::fake();

        $landingSubscription = LandingSubscription::factory()->create([
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'plan_id' => $this->plan->id,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Success
        $this->assertTrue($result['success']);

        // Assert: Temporary password ada
        $this->assertNotNull($result['temporary_password'], 'Temporary password should be set for new user');

        // Assert: Email dikirim
        Mail::assertSent(\App\Mail\WelcomeNewOwner::class, function ($mail) {
            return $mail->hasTo('newuser@example.com');
        });
    }

    public function test_provisioning_tidak_mengirim_email_untuk_existing_user(): void
    {
        // Arrange
        Mail::fake();

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
        ]);

        $landingSubscription = LandingSubscription::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'plan_id' => $this->plan->id,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Email tidak dikirim (karena existing user)
        Mail::assertNothingSent();

        // Assert: Tidak ada temporary password
        $this->assertNull($result['temporary_password'] ?? null);
    }

    public function test_semua_store_di_tenant_berbagi_subscription_yang_sama(): void
    {
        // Arrange
        $landingSubscription = LandingSubscription::factory()->create([
            'plan_id' => $this->plan->id,
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act: Provision
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);
        $tenant = $result['tenant'];
        $subscription = $result['subscription'];

        // Act: Buat store kedua untuk tenant yang sama
        $store2 = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Store 2',
        ]);

        // Assert: Store kedua juga pakai subscription yang sama
        $activeSubscription = $tenant->activeSubscription();
        $this->assertNotNull($activeSubscription);
        $this->assertEquals($subscription->id, $activeSubscription->id);
        $this->assertEquals($result['store']->tenant_id, $store2->tenant_id);
    }

    public function test_provisioning_authenticated_flow_menggunakan_tenant_yang_sudah_ada(): void
    {
        // Arrange: Create tenant dan user yang sudah ada (authenticated flow)
        $tenant = Tenant::factory()->create([
            'name' => 'Existing Tenant',
            'email' => 'tenant@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'name' => 'Existing Owner',
        ]);

        // Buat user_tenant_access
        DB::table('user_tenant_access')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create landing subscription dengan user_id & tenant_id (authenticated checkout)
        $landingSubscription = LandingSubscription::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'stage' => 'payment_pending',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 99000,
        ]);

        // Act: Provision
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Success
        $this->assertTrue($result['success']);

        // Assert: Menggunakan tenant & user yang sudah ada (tidak membuat baru)
        $this->assertEquals($tenant->id, $result['tenant']->id);
        $this->assertEquals($user->id, $result['user']->id);

        // Assert: Subscription dibuat untuk tenant yang sudah ada
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        // Assert: Store dibuat (karena tenant belum punya store)
        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenant->id,
        ]);

        // Assert: landing_subscription terupdate
        $landingSubscription->refresh();
        $this->assertNotNull($landingSubscription->subscription_id);
        $this->assertEquals('provisioned', $landingSubscription->status);
    }

    public function test_provisioning_authenticated_flow_tidak_membuat_store_jika_sudah_ada(): void
    {
        // Arrange: Tenant sudah punya store
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        DB::table('user_tenant_access')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $existingStore = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Store',
        ]);

        $landingSubscription = LandingSubscription::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $payment = SubscriptionPayment::factory()->create([
            'landing_subscription_id' => $landingSubscription->id,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Act
        $result = $this->service->provisionFromPaidLandingSubscription($landingSubscription, $payment);

        // Assert: Menggunakan store yang sudah ada
        $this->assertTrue($result['success']);
        $this->assertEquals($existingStore->id, $result['store']->id);

        // Assert: Tidak ada store baru yang dibuat
        $this->assertEquals(1, Store::where('tenant_id', $tenant->id)->count());
    }
}

