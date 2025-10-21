<?php

namespace Tests\Feature;

use App\Enums\AssignmentRoleEnum;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreUserAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create();
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin_sistem');
    }

    public function test_can_create_store_assignment()
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/store-assignments', [
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'assignment_role' => AssignmentRoleEnum::STAFF->value,
            'is_primary' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'User assigned to store successfully'
            ]);

        $this->assertDatabaseHas('store_user_assignments', [
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'assignment_role' => AssignmentRoleEnum::STAFF->value,
            'is_primary' => true,
        ]);
    }

    public function test_cannot_create_duplicate_assignment()
    {
        Sanctum::actingAs($this->adminUser);

        // Create first assignment
        StoreUserAssignment::factory()->forUserAndStore($this->user, $this->store)->create();

        // Try to create duplicate
        $response = $this->postJson('/api/v1/store-assignments', [
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'assignment_role' => AssignmentRoleEnum::MANAGER->value,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'User is already assigned to this store'
            ]);
    }

    public function test_can_update_assignment()
    {
        Sanctum::actingAs($this->adminUser);

        $assignment = StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $this->store)
            ->staff()
            ->create();

        $response = $this->putJson("/api/v1/store-assignments/{$assignment->id}", [
            'assignment_role' => AssignmentRoleEnum::MANAGER->value,
            'is_primary' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment updated successfully'
            ]);

        $assignment->refresh();
        $this->assertEquals(AssignmentRoleEnum::MANAGER, $assignment->assignment_role);
        $this->assertTrue($assignment->is_primary);
    }

    public function test_can_delete_assignment()
    {
        Sanctum::actingAs($this->adminUser);

        $assignment = StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $this->store)
            ->secondary()
            ->create();

        $response = $this->deleteJson("/api/v1/store-assignments/{$assignment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User removed from store successfully'
            ]);

        $this->assertDatabaseMissing('store_user_assignments', [
            'id' => $assignment->id,
        ]);
    }

    public function test_can_get_user_stores()
    {
        Sanctum::actingAs($this->adminUser);

        $store2 = Store::factory()->create();
        
        StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $this->store)
            ->owner()
            ->create();
            
        StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $store2)
            ->manager()
            ->create();

        $response = $this->getJson("/api/v1/store-assignments/users/{$this->user->id}/stores");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_can_set_primary_store()
    {
        Sanctum::actingAs($this->user);

        $assignment = StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $this->store)
            ->secondary()
            ->create();

        $response = $this->postJson("/api/v1/store-assignments/users/{$this->user->id}/primary-store", [
            'store_id' => $this->store->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Primary store updated successfully'
            ]);

        $assignment->refresh();
        $this->assertTrue($assignment->is_primary);
    }

    public function test_assignment_role_enum_methods()
    {
        $ownerRole = AssignmentRoleEnum::OWNER;
        $staffRole = AssignmentRoleEnum::STAFF;

        $this->assertTrue($ownerRole->hasOwnerPermissions());
        $this->assertTrue($ownerRole->hasAdminPermissions());
        $this->assertTrue($ownerRole->hasManagementPermissions());

        $this->assertFalse($staffRole->hasOwnerPermissions());
        $this->assertFalse($staffRole->hasAdminPermissions());
        $this->assertFalse($staffRole->hasManagementPermissions());

        $this->assertTrue($ownerRole->canManage($staffRole));
        $this->assertFalse($staffRole->canManage($ownerRole));

        $this->assertEquals('Store Owner', $ownerRole->getDisplayName());
        $this->assertEquals('Staff', $staffRole->getDisplayName());
    }

    public function test_store_user_assignment_model_methods()
    {
        $assignment = StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $this->store)
            ->owner()
            ->create();

        $this->assertTrue($assignment->hasOwnerPermissions());
        $this->assertTrue($assignment->hasAdminPermissions());
        $this->assertTrue($assignment->hasManagementPermissions());
        $this->assertEquals('Store Owner', $assignment->role_display_name);

        $staffAssignment = StoreUserAssignment::factory()
            ->forUserAndStore($this->user, $this->store)
            ->staff()
            ->create();

        $this->assertTrue($assignment->canManage($staffAssignment));
        $this->assertFalse($staffAssignment->canManage($assignment));
    }
}