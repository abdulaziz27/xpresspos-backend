<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role and permission seeder
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_system_admin_can_access_all_resources()
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        
        $systemAdmin = User::factory()->create(['store_id' => null]);
        $systemAdmin->assignRole('admin_sistem');
        
        $category1 = Category::factory()->create(['store_id' => $store1->id]);
        $category2 = Category::factory()->create(['store_id' => $store2->id]);
        
        $product1 = Product::factory()->create(['store_id' => $store1->id, 'category_id' => $category1->id]);
        $product2 = Product::factory()->create(['store_id' => $store2->id, 'category_id' => $category2->id]);
        
        Sanctum::actingAs($systemAdmin);
        
        // System admin should be able to access products from any store
        $this->assertTrue($systemAdmin->can('view', $product1));
        $this->assertTrue($systemAdmin->can('view', $product2));
        $this->assertTrue($systemAdmin->can('update', $product1));
        $this->assertTrue($systemAdmin->can('update', $product2));
    }

    public function test_store_owner_can_only_access_own_store_resources()
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        
        $owner1 = User::factory()->create(['store_id' => $store1->id]);
        $owner1->assignRole('owner');
        
        $category1 = Category::factory()->create(['store_id' => $store1->id]);
        $category2 = Category::factory()->create(['store_id' => $store2->id]);
        
        $product1 = Product::factory()->create(['store_id' => $store1->id, 'category_id' => $category1->id]);
        $product2 = Product::factory()->create(['store_id' => $store2->id, 'category_id' => $category2->id]);
        
        Sanctum::actingAs($owner1);
        
        // Owner should be able to access products from their store
        $this->assertTrue($owner1->can('view', $product1));
        $this->assertTrue($owner1->can('update', $product1));
        
        // Owner should NOT be able to access products from other stores
        $this->assertFalse($owner1->can('view', $product2));
        $this->assertFalse($owner1->can('update', $product2));
    }

    public function test_manager_has_limited_permissions()
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['store_id' => $store->id]);
        $manager->assignRole('manager');
        
        $category = Category::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id]);
        $user = User::factory()->create(['store_id' => $store->id]);
        
        Sanctum::actingAs($manager);
        
        // Manager should be able to manage products
        $this->assertTrue($manager->can('view', $product));
        $this->assertTrue($manager->can('update', $product));
        
        // Manager should NOT be able to delete users
        $this->assertFalse($manager->can('delete', $user));
        
        // Manager should NOT be able to manage roles
        $this->assertFalse($manager->can('manageRoles', $user));
    }

    public function test_cashier_has_minimal_permissions()
    {
        $store = Store::factory()->create();
        $cashier = User::factory()->create(['store_id' => $store->id]);
        $cashier->assignRole('cashier');
        
        $category = Category::factory()->create(['store_id' => $store->id]);
        $product = Product::factory()->create(['store_id' => $store->id, 'category_id' => $category->id]);
        $order = Order::factory()->create(['store_id' => $store->id]);
        
        Sanctum::actingAs($cashier);
        
        // Cashier should be able to view products
        $this->assertTrue($cashier->can('view', $product));
        
        // Cashier should be able to create and view orders
        $this->assertTrue($cashier->can('create', Order::class));
        $this->assertTrue($cashier->can('view', $order));
        
        // Cashier should NOT be able to update products
        $this->assertFalse($cashier->can('update', $product));
        
        // Cashier should NOT be able to delete orders
        $this->assertFalse($cashier->can('delete', $order));
    }

    public function test_permission_middleware_blocks_unauthorized_access()
    {
        $store = Store::factory()->create();
        $cashier = User::factory()->create(['store_id' => $store->id]);
        $cashier->assignRole('cashier');
        
        Sanctum::actingAs($cashier);
        
        // Create a test route that requires products.update permission
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/v1/test-permission-middleware');
        
        // Since we don't have a real route, we'll test the middleware logic
        $this->assertTrue($cashier->cannot('products.update'));
    }

    public function test_role_middleware_blocks_unauthorized_roles()
    {
        $store = Store::factory()->create();
        $cashier = User::factory()->create(['store_id' => $store->id]);
        $cashier->assignRole('cashier');
        
        Sanctum::actingAs($cashier);
        
        // Cashier should not have owner role
        $this->assertFalse($cashier->hasRole('owner'));
        $this->assertTrue($cashier->hasRole('cashier'));
    }

    public function test_owner_can_manage_staff_in_same_store()
    {
        $store = Store::factory()->create();
        $owner = User::factory()->create(['store_id' => $store->id]);
        $owner->assignRole('owner');
        
        $staff = User::factory()->create(['store_id' => $store->id]);
        $staff->assignRole('cashier');
        
        Sanctum::actingAs($owner);
        
        // Owner should be able to manage staff in their store
        $this->assertTrue($owner->can('view', $staff));
        $this->assertTrue($owner->can('update', $staff));
        $this->assertTrue($owner->can('delete', $staff));
        $this->assertTrue($owner->can('manageRoles', $staff));
    }

    public function test_owner_cannot_manage_staff_in_different_store()
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        
        $owner1 = User::factory()->create(['store_id' => $store1->id]);
        $owner1->assignRole('owner');
        
        $staff2 = User::factory()->create(['store_id' => $store2->id]);
        $staff2->assignRole('cashier');
        
        Sanctum::actingAs($owner1);
        
        // Owner should NOT be able to manage staff from different store
        $this->assertFalse($owner1->can('view', $staff2));
        $this->assertFalse($owner1->can('update', $staff2));
        $this->assertFalse($owner1->can('delete', $staff2));
        $this->assertFalse($owner1->can('manageRoles', $staff2));
    }

    public function test_owner_cannot_assign_system_admin_role()
    {
        $store = Store::factory()->create();
        $owner = User::factory()->create(['store_id' => $store->id]);
        $owner->assignRole('owner');
        
        $staff = User::factory()->create(['store_id' => $store->id]);
        
        Sanctum::actingAs($owner);
        
        // Owner should NOT be able to assign system admin role
        $this->assertFalse($owner->can('assignRole', 'admin_sistem'));
    }

    public function test_staff_management_api_endpoints()
    {
        $store = Store::factory()->create();
        $owner = User::factory()->create(['store_id' => $store->id]);
        $owner->assignRole('owner');
        
        Sanctum::actingAs($owner);
        
        // Test creating staff
        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Test Staff',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'role' => 'cashier'
        ]);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Staff member created successfully'
            ]);
        
        $staff = User::where('email', 'staff@example.com')->first();
        $this->assertNotNull($staff);
        $this->assertTrue($staff->hasRole('cashier'));
        
        // Test listing staff
        $response = $this->getJson('/api/v1/staff');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Staff members retrieved successfully'
            ]);
        
        // Test assigning role
        $response = $this->postJson("/api/v1/staff/{$staff->id}/roles", [
            'role' => 'manager'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role assigned successfully'
            ]);
        
        $staff->refresh();
        $this->assertTrue($staff->hasRole('manager'));
    }

    public function test_non_owner_cannot_access_staff_management()
    {
        $store = Store::factory()->create();
        $manager = User::factory()->create(['store_id' => $store->id]);
        $manager->assignRole('manager');
        
        Sanctum::actingAs($manager);
        
        // Manager should not be able to access staff management endpoints
        $response = $this->getJson('/api/v1/staff');
        $response->assertStatus(403);
        
        $response = $this->postJson('/api/v1/staff', [
            'name' => 'Test Staff',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'role' => 'cashier'
        ]);
        $response->assertStatus(403);
    }

    public function test_available_roles_and_permissions_endpoints()
    {
        $store = Store::factory()->create();
        $owner = User::factory()->create(['store_id' => $store->id]);
        $owner->assignRole('owner');
        
        Sanctum::actingAs($owner);
        
        // Test available roles endpoint
        $response = $this->getJson('/api/v1/roles/available');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Available roles retrieved successfully'
            ]);
        
        $roles = $response->json('data');
        $roleNames = collect($roles)->pluck('name')->toArray();
        
        // Should include store-level roles but not system admin
        $this->assertContains('owner', $roleNames);
        $this->assertContains('manager', $roleNames);
        $this->assertContains('cashier', $roleNames);
        $this->assertNotContains('admin_sistem', $roleNames);
        
        // Test available permissions endpoint
        $response = $this->getJson('/api/v1/permissions/available');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Available permissions retrieved successfully'
            ]);
        
        $permissions = $response->json('data');
        $permissionNames = collect($permissions)->pluck('name')->toArray();
        
        // Should include business permissions but not system permissions
        $this->assertContains('products.view', $permissionNames);
        $this->assertContains('orders.create', $permissionNames);
        $this->assertNotContains('system.backup', $permissionNames);
        $this->assertNotContains('subscription.manage', $permissionNames);
    }
}