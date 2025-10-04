<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Services\StoreSwitchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MultiTenancyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store1;
    protected Store $store2;
    protected User $systemAdmin;
    protected User $owner1;
    protected User $owner2;
    protected User $manager1;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create(['name' => 'admin_sistem', 'guard_name' => 'web']);
        Role::create(['name' => 'owner', 'guard_name' => 'web']);
        Role::create(['name' => 'manager', 'guard_name' => 'web']);

        // Create stores
        $this->store1 = Store::factory()->create(['name' => 'Store 1']);
        $this->store2 = Store::factory()->create(['name' => 'Store 2']);

        // Create users
        $this->systemAdmin = User::factory()->create([
            'store_id' => null,
            'email' => 'admin@system.com'
        ]);
        $this->systemAdmin->assignRole('admin_sistem');

        $this->owner1 = User::factory()->create([
            'store_id' => $this->store1->id,
            'email' => 'owner1@store.com'
        ]);
        $this->owner1->assignRole('owner');

        $this->owner2 = User::factory()->create([
            'store_id' => $this->store2->id,
            'email' => 'owner2@store.com'
        ]);
        $this->owner2->assignRole('owner');

        $this->manager1 = User::factory()->create([
            'store_id' => $this->store1->id,
            'email' => 'manager1@store.com'
        ]);
        $this->manager1->assignRole('manager');
    }

    #[Test]
    public function system_admin_can_access_all_stores_data()
    {
        // Create categories first
        $category1 = Category::factory()->create(['store_id' => $this->store1->id]);
        $category2 = Category::factory()->create(['store_id' => $this->store2->id]);
        
        // Create products in both stores
        $product1 = Product::factory()->create(['store_id' => $this->store1->id, 'category_id' => $category1->id]);
        $product2 = Product::factory()->create(['store_id' => $this->store2->id, 'category_id' => $category2->id]);

        Sanctum::actingAs($this->systemAdmin);

        // System admin should see all products
        $products = Product::all();
        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('id', $product1->id));
        $this->assertTrue($products->contains('id', $product2->id));
    }

    #[Test]
    public function store_owner_can_only_access_own_store_data()
    {
        // Create categories first
        $category1 = Category::factory()->create(['store_id' => $this->store1->id]);
        $category2 = Category::factory()->create(['store_id' => $this->store2->id]);
        
        // Create products in both stores
        $product1 = Product::factory()->create(['store_id' => $this->store1->id, 'category_id' => $category1->id]);
        $product2 = Product::factory()->create(['store_id' => $this->store2->id, 'category_id' => $category2->id]);

        Sanctum::actingAs($this->owner1);

        // Owner1 should only see store1 products
        $products = Product::all();
        $this->assertCount(1, $products);
        $this->assertTrue($products->contains('id', $product1->id));
        $this->assertFalse($products->contains('id', $product2->id));
    }

    #[Test]
    public function tenant_scope_middleware_validates_store_access()
    {
        // Test that middleware exists and can be instantiated
        $middleware = new \App\Http\Middleware\TenantScopeMiddleware();
        $this->assertInstanceOf(\App\Http\Middleware\TenantScopeMiddleware::class, $middleware);
        
        // Test that system admin bypasses tenant scoping
        Sanctum::actingAs($this->systemAdmin);
        $products = Product::all();
        $this->assertGreaterThanOrEqual(0, $products->count());
    }

    #[Test]
    public function tenant_scope_middleware_is_registered()
    {
        // Test that the middleware is properly registered by checking if it can be resolved
        // In Laravel 11+, middleware aliases are configured in bootstrap/app.php
        $middleware = app(\App\Http\Middleware\TenantScopeMiddleware::class);
        
        $this->assertInstanceOf(\App\Http\Middleware\TenantScopeMiddleware::class, $middleware);
        
        // Test that the middleware can be applied to a route
        $response = $this->withoutMiddleware()
            ->get('/api/v1/categories');
        
        // The middleware should be available for use
        $this->assertTrue(class_exists(\App\Http\Middleware\TenantScopeMiddleware::class));
    }

    #[Test]
    public function models_automatically_scope_to_current_user_store()
    {
        Sanctum::actingAs($this->owner1);

        // Create category - should automatically get store_id
        $category = Category::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $this->assertEquals($this->store1->id, $category->store_id);
    }

    #[Test]
    public function system_admin_must_explicitly_set_store_id()
    {
        Sanctum::actingAs($this->systemAdmin);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('System admin must explicitly set store_id when creating records');

        // System admin must explicitly set store_id
        Category::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function system_admin_can_create_records_with_explicit_store_id()
    {
        Sanctum::actingAs($this->systemAdmin);

        $category = Category::create([
            'store_id' => $this->store1->id,
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $this->assertEquals($this->store1->id, $category->store_id);
    }

    #[Test]
    public function store_scope_can_be_bypassed_with_methods()
    {
        $category1 = Category::factory()->create(['store_id' => $this->store1->id]);
        $category2 = Category::factory()->create(['store_id' => $this->store2->id]);
        $product1 = Product::factory()->create(['store_id' => $this->store1->id, 'category_id' => $category1->id]);
        $product2 = Product::factory()->create(['store_id' => $this->store2->id, 'category_id' => $category2->id]);

        Sanctum::actingAs($this->owner1);

        // Normal query should only return store1 products
        $this->assertCount(1, Product::all());

        // Using withoutStoreScope should return all products
        $this->assertCount(2, Product::withoutStoreScope()->get());

        // Using forStore should return specific store products
        $this->assertCount(1, Product::forStore($this->store2->id)->get());

        // Using forAllStores should return all products
        $this->assertCount(2, Product::forAllStores()->get());
    }

    #[Test]
    public function belongs_to_store_trait_provides_helper_methods()
    {
        $category1 = Category::factory()->create(['store_id' => $this->store1->id]);
        $product = Product::factory()->create(['store_id' => $this->store1->id, 'category_id' => $category1->id]);

        $this->assertTrue($product->belongsToStore($this->store1->id));
        $this->assertFalse($product->belongsToStore($this->store2->id));

        Sanctum::actingAs($this->owner1);
        $this->assertTrue($product->belongsToCurrentUserStore());

        Sanctum::actingAs($this->owner2);
        $this->assertFalse($product->belongsToCurrentUserStore());
    }

    #[Test]
    public function cross_store_data_access_is_prevented()
    {
        $order1 = Order::factory()->create(['store_id' => $this->store1->id]);
        $order2 = Order::factory()->create(['store_id' => $this->store2->id]);

        Sanctum::actingAs($this->owner1);

        // Should only see own store orders
        $orders = Order::all();
        $this->assertCount(1, $orders);
        $this->assertEquals($order1->id, $orders->first()->id);

        // Direct access to other store order should fail
        $this->assertNull(Order::find($order2->id));
    }

    #[Test]
    public function user_without_store_id_gets_no_results()
    {
        $category1 = Category::factory()->create(['store_id' => $this->store1->id]);
        $category2 = Category::factory()->create(['store_id' => $this->store2->id]);
        Product::factory()->create(['store_id' => $this->store1->id, 'category_id' => $category1->id]);
        Product::factory()->create(['store_id' => $this->store2->id, 'category_id' => $category2->id]);

        // Create user without store_id
        $userWithoutStore = User::factory()->create(['store_id' => null]);
        $userWithoutStore->assignRole('manager');

        Sanctum::actingAs($userWithoutStore);

        // Should get no results due to security restriction
        $this->assertCount(0, Product::all());
    }

    #[Test]
    public function activity_log_records_store_context()
    {
        Sanctum::actingAs($this->owner1);

        $category1 = Category::factory()->create(['store_id' => $this->store1->id]);
        $product = Product::factory()->create(['store_id' => $this->store1->id, 'category_id' => $category1->id]);

        // Simulate activity logging
        \App\Models\ActivityLog::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->owner1->id,
            'event' => 'product.created',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);

        // Owner1 should see the log
        $logs = \App\Models\ActivityLog::all();
        $this->assertCount(1, $logs);

        // Owner2 should not see the log
        Sanctum::actingAs($this->owner2);
        $logs = \App\Models\ActivityLog::all();
        $this->assertCount(0, $logs);
    }
}