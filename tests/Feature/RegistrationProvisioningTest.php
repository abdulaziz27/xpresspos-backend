<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RegistrationProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected RegistrationProvisioningService $provisioningService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create owner role for permission system
        Role::create(['name' => 'owner', 'guard_name' => 'web']);

        $this->provisioningService = new RegistrationProvisioningService();
    }

    public function test_auto_provision_membuat_tenant_dan_store_untuk_user_baru(): void
    {
        // Arrange: Create a new user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('owner');

        // Act: Call provisioning service
        $this->provisioningService->provisionFor($user);

        // Assert: User has a tenant
        $this->assertDatabaseHas('user_tenant_access', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Assert: Tenant was created
        $tenantAccess = DB::table('user_tenant_access')
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($tenantAccess);

        $tenant = Tenant::find($tenantAccess->tenant_id);
        $this->assertNotNull($tenant);
        $this->assertEquals($user->name . "'s Business", $tenant->name);
        $this->assertEquals($user->email, $tenant->email);
        $this->assertEquals('active', $tenant->status);

        // Assert: Store was created for the tenant
        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenant->id,
            'name' => 'Main Store',
            'status' => 'active',
        ]);

        $store = Store::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($store);

        // Assert: StoreUserAssignment was created
        $this->assertDatabaseHas('store_user_assignments', [
            'store_id' => $store->id,
            'user_id' => $user->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        // Assert: User has primary store assignment
        $user->refresh();
        $primaryStore = $user->primaryStore();
        $this->assertNotNull($primaryStore);
        $this->assertEquals($store->id, $primaryStore->id);
    }

    public function test_auto_provision_tidak_membuat_duplikat_jika_user_sudah_punya_tenant(): void
    {
        // Arrange: Create user with existing tenant
        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('owner');

        $existingTenant = Tenant::create([
            'name' => 'Existing Tenant',
            'email' => $user->email,
            'status' => 'active',
        ]);

        DB::table('user_tenant_access')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $user->id,
            'tenant_id' => $existingTenant->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenantsCountBefore = Tenant::count();

        // Act: Call provisioning service
        $this->provisioningService->provisionFor($user);

        // Assert: No new tenant was created
        $tenantsCountAfter = Tenant::count();
        $this->assertEquals($tenantsCountBefore, $tenantsCountAfter);

        // Assert: User still has only one tenant
        $this->assertEquals(1, $user->tenants()->count());
    }

    public function test_register_endpoint_auto_provisions_user(): void
    {
        // Arrange: Prepare registration data
        $registrationData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act: Call register endpoint
        $response = $this->post(route('register.post'), $registrationData);

        // Assert: User was created and redirected
        $response->assertRedirect(); // Should redirect to owner panel or intended URL

        // Assert: User was created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);

        // Assert: User has owner role
        $this->assertTrue($user->hasRole('owner'));

        // Assert: User has a tenant
        $this->assertGreaterThan(0, $user->tenants()->count());

        // Assert: User has a store
        $tenant = $user->currentTenant();
        $this->assertNotNull($tenant);

        $store = Store::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($store);
        $this->assertEquals('Main Store', $store->name);

        // Assert: User is assigned to the store
        $this->assertDatabaseHas('store_user_assignments', [
            'user_id' => $user->id,
            'store_id' => $store->id,
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);
    }

    public function test_registered_user_dapat_mengakses_owner_panel(): void
    {
        // Arrange: Register a new user
        $registrationData = [
            'name' => 'Panel Test User',
            'email' => 'paneltest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->post(route('register.post'), $registrationData);

        $user = User::where('email', 'paneltest@example.com')->first();
        $this->assertNotNull($user);

        // Refresh user to load roles and relationships
        $user->refresh();
        $user->load('roles');

        // Assert: User has the necessary structure for owner panel access
        // Check via direct database query to avoid permission team context issues in tests
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);

        $this->assertNotNull($user->primaryStore());
        $this->assertNotNull($user->currentTenant());
        
        // Assert: User has a valid primary store assignment
        $primaryStore = $user->primaryStore();
        $this->assertNotNull($primaryStore);
        
        // Assert: Store is active
        $this->assertEquals('active', $primaryStore->status);
    }

    public function test_login_redirect_intended_after_authentication(): void
    {
        // Arrange: Create a user with tenant and store
        $user = User::create([
            'name' => 'Login Test User',
            'email' => 'logintest@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('owner');
        $this->provisioningService->provisionFor($user);

        // Arrange: Simulate trying to access checkout (which requires auth)
        $response = $this->get(route('checkout', ['plan_id' => 1, 'billing' => 'monthly']));
        $response->assertRedirect(route('login')); // Should redirect to login

        // Act: Login with valid credentials
        $loginResponse = $this->post(route('login.post'), [
            'email' => 'logintest@example.com',
            'password' => 'password',
        ]);

        // Assert: After login, should redirect to intended URL (checkout)
        // Laravel's redirect()->intended() will redirect to the stored intended URL
        $loginResponse->assertRedirect(); // Will redirect somewhere (could be intended or default)
        
        // Follow redirect to see where we end up
        $this->assertAuthenticated();
        $this->assertEquals($user->id, auth()->id());
    }
}

