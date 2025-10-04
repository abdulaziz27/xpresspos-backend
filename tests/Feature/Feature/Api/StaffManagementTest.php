<?php

namespace Tests\Feature\Feature\Api;

use App\Models\Store;
use App\Models\User;
use App\Models\StaffInvitation;
use App\Models\StaffPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $store;
    protected $owner;
    protected $manager;
    protected $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create test store
        $this->store = Store::factory()->create();

        // Create users with different roles
        $this->owner = User::factory()->create(['store_id' => $this->store->id]);
        $this->owner->assignRole('owner');

        $this->manager = User::factory()->create(['store_id' => $this->store->id]);
        $this->manager->assignRole('manager');

        $this->cashier = User::factory()->create(['store_id' => $this->store->id]);
        $this->cashier->assignRole('cashier');
    }

    public function test_owner_can_list_staff_members()
    {
        Sanctum::actingAs($this->owner);

        $response = $this->getJson('/api/v1/staff');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'roles',
                            'permissions'
                        ]
                    ],
                    'message'
                ]);

        // Should not include the owner themselves or system admins
        $staffIds = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($this->owner->id, $staffIds);
    }

    public function test_owner_can_create_staff_member()
    {
        Sanctum::actingAs($this->owner);

        $staffData = [
            'name' => 'New Staff Member',
            'email' => 'newstaff@example.com',
            'password' => 'password123',
            'role' => 'cashier',
        ];

        $response = $this->postJson('/api/v1/staff', $staffData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'email',
                        'roles',
                        'permissions'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newstaff@example.com',
            'store_id' => $this->store->id,
        ]);
    }

    public function test_owner_can_send_staff_invitation()
    {
        Sanctum::actingAs($this->owner);

        $invitationData = [
            'name' => 'Invited Staff',
            'email' => 'invited@example.com',
            'role' => 'manager',
            'expires_in_days' => 7,
        ];

        $response = $this->postJson('/api/v1/staff/invite', $invitationData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'email',
                        'name',
                        'role',
                        'token',
                        'expires_at',
                        'invited_by'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('staff_invitations', [
            'email' => 'invited@example.com',
            'store_id' => $this->store->id,
            'invited_by' => $this->owner->id,
            'status' => 'pending',
        ]);
    }

    public function test_owner_can_view_invitations()
    {
        Sanctum::actingAs($this->owner);

        // Create some invitations
        StaffInvitation::factory()->create([
            'store_id' => $this->store->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->getJson('/api/v1/staff/invitations');



        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'email',
                            'name',
                            'role',
                            'status',
                            'invited_by'
                        ]
                    ],
                    'message'
                ]);
    }

    public function test_owner_can_cancel_pending_invitation()
    {
        Sanctum::actingAs($this->owner);

        $invitation = StaffInvitation::factory()->create([
            'store_id' => $this->store->id,
            'invited_by' => $this->owner->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/staff/invitations/{$invitation->id}/cancel");

        $response->assertStatus(200);

        $this->assertDatabaseHas('staff_invitations', [
            'id' => $invitation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_invitation_can_be_accepted()
    {
        $invitation = StaffInvitation::factory()->create([
            'store_id' => $this->store->id,
            'invited_by' => $this->owner->id,
            'status' => 'pending',
            'email' => 'newuser@example.com',
        ]);

        $acceptanceData = [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", $acceptanceData);



        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'roles'
                        ],
                        'token',
                        'store'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'store_id' => $this->store->id,
        ]);

        $this->assertDatabaseHas('staff_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
    }

    public function test_owner_can_assign_roles_to_staff()
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/staff/{$this->cashier->id}/roles", [
            'role' => 'manager'
        ]);

        $response->assertStatus(200);

        $this->assertTrue($this->cashier->fresh()->hasRole('manager'));
    }

    public function test_owner_can_grant_permissions_to_staff()
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/v1/staff/{$this->cashier->id}/permissions", [
            'permission' => 'products.create'
        ]);

        $response->assertStatus(200);

        $this->assertTrue($this->cashier->fresh()->hasPermissionTo('products.create'));
    }

    public function test_owner_can_view_activity_logs()
    {
        Sanctum::actingAs($this->owner);

        // Create some activity logs
        \App\Models\ActivityLog::create([
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'event' => 'order.created',
            'auditable_type' => 'App\Models\Order',
            'auditable_id' => 1,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ]);

        $response = $this->getJson('/api/v1/staff/activity-logs');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'event',
                                'user',
                                'created_at'
                            ]
                        ]
                    ],
                    'message'
                ]);
    }

    public function test_owner_can_view_staff_performance()
    {
        Sanctum::actingAs($this->owner);

        // Create performance data
        StaffPerformance::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
            'date' => now()->format('Y-m-d'),
            'orders_processed' => 10,
            'total_sales' => 1000.00,
        ]);

        $response = $this->getJson("/api/v1/staff/{$this->cashier->id}/performance");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'performances',
                        'summary',
                        'period'
                    ],
                    'message'
                ]);
    }

    public function test_owner_can_update_staff_performance()
    {
        Sanctum::actingAs($this->owner);

        $performanceData = [
            'date' => now()->format('Y-m-d'),
            'orders_processed' => 15,
            'total_sales' => 1500.00,
            'hours_worked' => 8,
            'customer_satisfaction_score' => 4.5,
        ];

        $response = $this->postJson("/api/v1/staff/{$this->cashier->id}/performance", $performanceData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'orders_processed',
                        'total_sales',
                        'user'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('staff_performances', [
            'user_id' => $this->cashier->id,
            'store_id' => $this->store->id,
            'orders_processed' => 15,
            'total_sales' => 1500.00,
        ]);
    }

    public function test_non_owner_cannot_manage_staff()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/staff/invite', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'role' => 'cashier',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_access_staff_from_different_store()
    {
        $otherStore = Store::factory()->create();
        $otherOwner = User::factory()->create(['store_id' => $otherStore->id]);
        $otherOwner->assignRole('owner');

        Sanctum::actingAs($this->owner);

        $response = $this->getJson("/api/v1/staff/{$otherOwner->id}");

        $response->assertStatus(403);
    }

    public function test_expired_invitation_cannot_be_accepted()
    {
        $invitation = StaffInvitation::factory()->create([
            'store_id' => $this->store->id,
            'invited_by' => $this->owner->id,
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v1/invitations/{$invitation->token}/accept", [
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(410);
    }
}
