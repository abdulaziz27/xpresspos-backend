<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\StoreSwitchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StoreSwitchingTest extends TestCase
{
    use DatabaseTransactions;

    protected Store $store1;
    protected Store $store2;
    protected User $systemAdmin;
    protected User $owner;
    protected StoreSwitchingService $storeSwitchingService;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations and seeders
        $this->artisan('migrate:fresh --seed');

        // Roles are already created by seeder

        // Create stores
        $this->store1 = Store::factory()->create(['name' => 'Store 1']);
        $this->store2 = Store::factory()->create(['name' => 'Store 2']);

        // Create users
        $this->systemAdmin = User::factory()->create([
            'store_id' => null,
            'email' => 'admin@system.com'
        ]);
        $this->systemAdmin->assignRole('admin_sistem');

        $this->owner = User::factory()->create([
            'store_id' => $this->store1->id,
            'email' => 'owner@store.com'
        ]);
        $this->owner->assignRole('owner');

        $this->storeSwitchingService = new StoreSwitchingService();
    }

    #[Test]
    public function system_admin_can_switch_to_store_context()
    {
        $result = $this->storeSwitchingService->switchStore($this->systemAdmin, $this->store1->id);

        $this->assertTrue($result);
        $this->assertEquals($this->store1->id, Session::get('admin_store_context'));
        $this->assertEquals($this->store1->id, $this->systemAdmin->fresh()->store_id);
    }

    #[Test]
    public function system_admin_can_clear_store_context()
    {
        // First switch to a store
        $this->storeSwitchingService->switchStore($this->systemAdmin, $this->store1->id);

        // Then clear context
        $result = $this->storeSwitchingService->clearStoreContext($this->systemAdmin);

        $this->assertTrue($result);
        $this->assertNull(Session::get('admin_store_context'));
        $this->assertNull($this->systemAdmin->fresh()->store_id);
    }

    #[Test]
    public function non_admin_cannot_switch_stores()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only system administrators can switch store context');

        $this->storeSwitchingService->switchStore($this->owner, $this->store2->id);
    }

    #[Test]
    public function non_admin_cannot_clear_store_context()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only system administrators can clear store context');

        $this->storeSwitchingService->clearStoreContext($this->owner);
    }

    #[Test]
    public function switching_to_nonexistent_store_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Store not found');

        $this->storeSwitchingService->switchStore($this->systemAdmin, 'nonexistent-id');
    }

    #[Test]
    public function get_available_stores_returns_all_stores_for_admin()
    {
        $stores = $this->storeSwitchingService->getAvailableStores($this->systemAdmin);

        $this->assertGreaterThanOrEqual(2, count($stores));

        // Check that our test stores are included
        $storeNames = collect($stores)->pluck('name')->toArray();
        $this->assertContains($this->store1->name, $storeNames);
        $this->assertContains($this->store2->name, $storeNames);
    }

    #[Test]
    public function get_available_stores_returns_empty_for_non_admin()
    {
        $stores = $this->storeSwitchingService->getAvailableStores($this->owner);

        $this->assertEmpty($stores);
    }

    #[Test]
    public function get_current_store_context_returns_session_value_for_admin()
    {
        Session::put('admin_store_context', $this->store1->id);

        $context = $this->storeSwitchingService->getCurrentStoreContext($this->systemAdmin);

        $this->assertEquals($this->store1->id, $context);
    }

    #[Test]
    public function get_current_store_context_returns_user_store_for_non_admin()
    {
        $context = $this->storeSwitchingService->getCurrentStoreContext($this->owner);

        $this->assertEquals($this->store1->id, $context);
    }

    #[Test]
    public function is_in_store_context_works_correctly()
    {
        // Admin without context
        $this->assertFalse($this->storeSwitchingService->isInStoreContext($this->systemAdmin));

        // Admin with context
        Session::put('admin_store_context', $this->store1->id);
        $this->assertTrue($this->storeSwitchingService->isInStoreContext($this->systemAdmin));

        // Regular user always in context
        $this->assertTrue($this->storeSwitchingService->isInStoreContext($this->owner));
    }

    #[Test]
    public function get_current_store_info_returns_store_details()
    {
        Session::put('admin_store_context', $this->store1->id);

        $info = $this->storeSwitchingService->getCurrentStoreInfo($this->systemAdmin);

        $this->assertEquals($this->store1->id, $info['id']);
        $this->assertEquals($this->store1->name, $info['name']);
        $this->assertEquals($this->store1->email, $info['email']);
    }

    #[Test]
    public function validate_store_access_works_correctly()
    {
        // Admin can access any store
        $this->assertTrue($this->storeSwitchingService->validateStoreAccess($this->systemAdmin, $this->store1->id));
        $this->assertTrue($this->storeSwitchingService->validateStoreAccess($this->systemAdmin, $this->store2->id));

        // Owner can only access own store
        $this->assertTrue($this->storeSwitchingService->validateStoreAccess($this->owner, $this->store1->id));
        $this->assertFalse($this->storeSwitchingService->validateStoreAccess($this->owner, $this->store2->id));
    }

    #[Test]
    public function store_switching_logs_activity()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('System admin store context switch', \Mockery::type('array'));

        $this->storeSwitchingService->switchStore($this->systemAdmin, $this->store1->id);
    }

    #[Test]
    public function store_switch_api_endpoint_works()
    {
        Sanctum::actingAs($this->systemAdmin);

        $response = $this->postJson('/api/v1/admin/stores/switch', [
            'store_id' => $this->store1->id
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'store_context' => [
                    'id' => $this->store1->id,
                    'name' => $this->store1->name,
                ]
            ]
        ]);
    }

    #[Test]
    public function store_switch_api_requires_admin_role()
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/admin/stores/switch', [
            'store_id' => $this->store1->id
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function store_switch_api_validates_store_exists()
    {
        Sanctum::actingAs($this->systemAdmin);

        $response = $this->postJson('/api/v1/admin/stores/switch', [
            'store_id' => 'nonexistent-id'
        ]);

        // The validation should fail with 422 status
        $response->assertStatus(422);

        // Check if it's a validation error or service error
        $responseData = $response->json();
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    #[Test]
    public function clear_context_api_endpoint_works()
    {
        Sanctum::actingAs($this->systemAdmin);

        // First switch to a store
        $this->storeSwitchingService->switchStore($this->systemAdmin, $this->store1->id);

        $response = $this->postJson('/api/v1/admin/stores/clear');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'message' => 'Returned to global admin view'
            ]
        ]);
    }

    #[Test]
    public function get_available_stores_api_endpoint_works()
    {
        Sanctum::actingAs($this->systemAdmin);

        $response = $this->getJson('/api/v1/admin/stores/');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'available_stores' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'status'
                    ]
                ],
                'current_context',
                'is_in_store_context'
            ],
            'message'
        ]);

        // Check that our test stores are included
        $availableStores = $response->json('data.available_stores');
        $storeNames = collect($availableStores)->pluck('name')->toArray();
        $this->assertContains($this->store1->name, $storeNames);
        $this->assertContains($this->store2->name, $storeNames);
    }

    #[Test]
    public function get_current_context_api_endpoint_works()
    {
        Sanctum::actingAs($this->systemAdmin);
        Session::put('admin_store_context', $this->store1->id);

        $response = $this->getJson('/api/v1/admin/stores/current');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'current_store' => [
                    'id' => $this->store1->id,
                    'name' => $this->store1->name,
                ],
                'is_in_store_context' => true,
                'can_switch_stores' => true,
            ]
        ]);
    }
}
