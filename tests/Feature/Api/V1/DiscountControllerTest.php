<?php

namespace Tests\Feature\Api\V1;

use App\Models\Discount;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscountControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        $this->user->assignRole('owner');

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_discounts(): void
    {
        Discount::factory()->count(3)->create(['store_id' => $this->store->id]);
        Discount::factory()->create(); // Other store discount should not appear

        $response = $this->getJson('/api/v1/discounts');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'store_id',
                        'name',
                        'description',
                        'type',
                        'value',
                        'status',
                        'expired_date',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'timestamp',
                    'version',
                    'applied_filters',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_only_active_discounts(): void
    {
        Discount::factory()->create([
            'store_id' => $this->store->id,
            'status' => Discount::STATUS_ACTIVE,
            'expired_date' => now()->addWeek()->toDateString(),
        ]);

        Discount::factory()->create([
            'store_id' => $this->store->id,
            'status' => Discount::STATUS_ACTIVE,
            'expired_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/discounts?only_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals(Discount::STATUS_ACTIVE, $response->json('data.0.status'));
    }

    public function test_can_create_discount(): void
    {
        $payload = [
            'name' => 'Weekend Promo',
            'description' => 'Discount for weekend shoppers',
            'type' => Discount::TYPE_PERCENTAGE,
            'value' => 15,
            'status' => Discount::STATUS_ACTIVE,
            'expired_date' => now()->addMonth()->toDateString(),
        ];

        $response = $this->postJson('/api/v1/discounts', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Weekend Promo')
            ->assertJsonPath('data.store_id', $this->store->id);

        $this->assertDatabaseHas('discounts', [
            'name' => 'Weekend Promo',
            'store_id' => $this->store->id,
            'type' => Discount::TYPE_PERCENTAGE,
        ]);
    }

    public function test_cannot_create_duplicate_discount_name_in_same_store(): void
    {
        Discount::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Happy Hour',
        ]);

        $response = $this->postJson('/api/v1/discounts', [
            'name' => 'Happy Hour',
            'type' => Discount::TYPE_FIXED,
            'value' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_create_discount_for_any_store(): void
    {
        $admin = User::factory()->create(['store_id' => null]);
        $admin->assignRole('admin_sistem');

        Sanctum::actingAs($admin);

        $otherStore = Store::factory()->create();

        $response = $this->postJson('/api/v1/discounts', [
            'store_id' => $otherStore->id,
            'name' => 'Admin Discount',
            'type' => Discount::TYPE_FIXED,
            'value' => 25000,
            'status' => Discount::STATUS_ACTIVE,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.store_id', $otherStore->id);

        $this->assertDatabaseHas('discounts', [
            'name' => 'Admin Discount',
            'store_id' => $otherStore->id,
        ]);
    }

    public function test_can_update_discount(): void
    {
        $discount = Discount::factory()->create([
            'store_id' => $this->store->id,
            'name' => 'Early Bird',
        ]);

        $response = $this->putJson("/api/v1/discounts/{$discount->id}", [
            'name' => 'Early Bird Updated',
            'description' => 'Updated desc',
            'type' => Discount::TYPE_FIXED,
            'value' => 20000,
            'status' => Discount::STATUS_INACTIVE,
            'expired_date' => now()->addWeeks(2)->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Early Bird Updated')
            ->assertJsonPath('data.status', Discount::STATUS_INACTIVE);

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'name' => 'Early Bird Updated',
            'status' => Discount::STATUS_INACTIVE,
        ]);
    }

    public function test_can_delete_discount(): void
    {
        $discount = Discount::factory()->create([
            'store_id' => $this->store->id,
        ]);

        $response = $this->deleteJson("/api/v1/discounts/{$discount->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Discount deleted successfully',
            ]);

        $this->assertDatabaseMissing('discounts', ['id' => $discount->id]);
    }

    public function test_cannot_view_discount_from_other_store(): void
    {
        $otherStore = Store::factory()->create();
        $foreignDiscount = Discount::factory()->create([
            'store_id' => $otherStore->id,
        ]);

        $response = $this->getJson("/api/v1/discounts/{$foreignDiscount->id}");

        $response->assertStatus(404);
    }
}
