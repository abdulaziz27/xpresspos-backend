<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberTier;
use App\Models\Store;
use App\Models\User;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions and roles
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        $this->user->assignRole('owner');
        
        // Initialize default member tiers
        $loyaltyService = app(LoyaltyService::class);
        $loyaltyService->initializeDefaultTiers($this->store->id);

        Sanctum::actingAs($this->user);
    }

    public function test_can_create_member_with_loyalty_system()
    {
        $memberData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'date_of_birth' => '1990-01-01',
            'address' => '123 Main St',
        ];

        $response = $this->postJson('/api/v1/members', $memberData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'member_number',
                    'name',
                    'email',
                    'phone',
                    'loyalty_points',
                    'current_tier_name',
                    'tier_discount_percentage',
                    'points_to_next_tier',
                ],
                'message'
            ]);

        $this->assertDatabaseHas('members', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'store_id' => $this->store->id,
            'loyalty_points' => 0,
        ]);
    }

    public function test_can_add_loyalty_points_with_transaction_tracking()
    {
        $member = Member::factory()->create([
            'store_id' => $this->store->id,
            'loyalty_points' => 0, // Start with 0 points
        ]);

        $response = $this->postJson("/api/v1/members/{$member->id}/loyalty-points/add", [
            'points' => 500,
            'reason' => 'Welcome bonus',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'points_added' => 500,
                    'new_balance' => 500,
                ]
            ]);

        $this->assertDatabaseHas('loyalty_point_transactions', [
            'member_id' => $member->id,
            'type' => 'earned',
            'points' => 500,
            'reason' => 'Welcome bonus',
        ]);

        $member->refresh();
        $this->assertEquals(500, $member->loyalty_points);
    }

    public function test_can_redeem_loyalty_points()
    {
        $member = Member::factory()->create([
            'store_id' => $this->store->id,
            'loyalty_points' => 1000,
        ]);

        $response = $this->postJson("/api/v1/members/{$member->id}/loyalty-points/redeem", [
            'points' => 200,
            'reason' => 'Discount redemption',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'points_redeemed' => 200,
                    'new_balance' => 800,
                ]
            ]);

        $this->assertDatabaseHas('loyalty_point_transactions', [
            'member_id' => $member->id,
            'type' => 'redeemed',
            'points' => -200,
        ]);

        $member->refresh();
        $this->assertEquals(800, $member->loyalty_points);
    }

    public function test_member_tier_system_works()
    {
        $member = Member::factory()->create([
            'store_id' => $this->store->id,
            'loyalty_points' => 0,
        ]);

        // Should start in Bronze tier
        $this->assertEquals('Bronze', $member->getCurrentTier()->name);

        // Add points to reach Silver tier
        $member->addLoyaltyPoints(1500, 'Purchase bonus');
        $member->refresh();

        $this->assertEquals('Silver', $member->getCurrentTier()->name);
        $this->assertEquals(5.00, $member->getTierDiscountPercentage());
    }

    public function test_can_get_member_statistics()
    {
        $member = Member::factory()->create(['store_id' => $this->store->id]);

        $response = $this->getJson("/api/v1/members/{$member->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_orders',
                    'completed_orders',
                    'average_order_value',
                    'current_tier',
                    'points_to_next_tier',
                    'activity_summary',
                    'recent_transactions',
                ]
            ]);
    }

    public function test_can_get_loyalty_history()
    {
        $member = Member::factory()->create(['store_id' => $this->store->id]);
        
        // Add some transactions
        $member->addLoyaltyPoints(100, 'Purchase');
        $member->redeemLoyaltyPoints(50, 'Discount');

        $response = $this->getJson("/api/v1/members/{$member->id}/loyalty-history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'points',
                        'reason',
                        'created_at',
                    ]
                ]
            ]);
    }

    public function test_can_get_member_tiers()
    {
        $response = $this->getJson('/api/v1/member-tiers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'min_points',
                        'max_points',
                        'discount_percentage',
                        'benefits',
                        'color',
                    ]
                ]
            ]);

        // Should have 4 default tiers
        $this->assertCount(4, $response->json('data'));
    }

    public function test_can_get_tier_statistics()
    {
        // Create members in different tiers
        Member::factory()->create(['store_id' => $this->store->id, 'loyalty_points' => 500]);
        Member::factory()->create(['store_id' => $this->store->id, 'loyalty_points' => 1500]);
        Member::factory()->create(['store_id' => $this->store->id, 'loyalty_points' => 6000]);

        $response = $this->getJson('/api/v1/member-tier-statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'tier',
                        'member_count',
                        'percentage',
                    ]
                ]
            ]);
    }

    public function test_cannot_redeem_more_points_than_available()
    {
        $member = Member::factory()->create([
            'store_id' => $this->store->id,
            'loyalty_points' => 100,
        ]);

        $response = $this->postJson("/api/v1/members/{$member->id}/loyalty-points/redeem", [
            'points' => 200,
            'reason' => 'Discount redemption',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INSUFFICIENT_POINTS',
                ]
            ]);
    }
}