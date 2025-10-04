<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableManagementTest extends TestCase
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
        
        Sanctum::actingAs($this->user);
    }

    public function test_can_create_table()
    {
        $tableData = [
            'table_number' => 'T001',
            'name' => 'Table 1',
            'capacity' => 4,
            'location' => 'Main dining area',
        ];

        $response = $this->postJson('/api/v1/tables', $tableData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'table_number',
                    'name',
                    'capacity',
                    'status',
                    'location',
                    'is_available',
                    'is_occupied',
                ],
                'message'
            ]);

        $this->assertDatabaseHas('tables', [
            'table_number' => 'T001',
            'name' => 'Table 1',
            'store_id' => $this->store->id,
            'status' => 'available',
        ]);
    }

    public function test_can_occupy_table_with_tracking()
    {
        $table = Table::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'available',
        ]);

        $response = $this->postJson("/api/v1/tables/{$table->id}/occupy", [
            'party_size' => 4,
            'notes' => 'Birthday celebration',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Table occupied successfully',
            ])
            ->assertJsonStructure([
                'meta' => [
                    'occupancy_id',
                    'occupied_at',
                ]
            ]);

        $table->refresh();
        $this->assertEquals('occupied', $table->status);
        $this->assertNotNull($table->occupied_at);
        $this->assertEquals(1, $table->total_occupancy_count);

        $this->assertDatabaseHas('table_occupancy_histories', [
            'table_id' => $table->id,
            'party_size' => 4,
            'status' => 'occupied',
            'notes' => 'Birthday celebration',
        ]);
    }

    public function test_can_make_table_available_with_duration_tracking()
    {
        $table = Table::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'occupied',
            'occupied_at' => now()->subMinutes(90),
        ]);

        // Create occupancy history
        $occupancy = $table->occupancyHistories()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'occupied_at' => $table->occupied_at,
            'party_size' => 2,
            'status' => 'occupied',
        ]);

        $response = $this->postJson("/api/v1/tables/{$table->id}/make-available");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Table made available successfully',
            ])
            ->assertJsonStructure([
                'meta' => [
                    'cleared_at',
                    'occupancy_duration',
                ]
            ]);

        $table->refresh();
        $this->assertEquals('available', $table->status);
        $this->assertNotNull($table->last_cleared_at);
        $this->assertNull($table->current_order_id);

        $occupancy->refresh();
        $this->assertEquals('cleared', $occupancy->status);
        $this->assertNotNull($occupancy->cleared_at);
        $this->assertNotNull($occupancy->duration_minutes);
    }

    public function test_can_get_table_occupancy_statistics()
    {
        $table = Table::factory()->create(['store_id' => $this->store->id]);

        $response = $this->getJson("/api/v1/tables/{$table->id}/occupancy-stats?days=30");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'table',
                    'period_days',
                    'statistics' => [
                        'total_occupancies',
                        'cleared_occupancies',
                        'average_duration',
                        'total_revenue',
                        'average_party_size',
                        'utilization_rate',
                    ]
                ]
            ]);
    }

    public function test_can_get_occupancy_history()
    {
        $table = Table::factory()->create(['store_id' => $this->store->id]);
        
        // Create some occupancy history
        $table->occupancyHistories()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'occupied_at' => now()->subHours(2),
            'cleared_at' => now()->subHours(1),
            'duration_minutes' => 60,
            'party_size' => 3,
            'status' => 'cleared',
        ]);

        $response = $this->getJson("/api/v1/tables/{$table->id}/occupancy-history");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'occupied_at',
                        'cleared_at',
                        'duration_minutes',
                        'party_size',
                        'status',
                    ]
                ]
            ]);
    }

    public function test_can_get_overall_occupancy_report()
    {
        // Create multiple tables
        Table::factory()->count(3)->create(['store_id' => $this->store->id]);

        $response = $this->getJson('/api/v1/table-occupancy-report');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_tables',
                        'occupied_tables',
                        'available_tables',
                        'maintenance_tables',
                    ],
                    'occupancy_details' => [
                        '*' => [
                            'table',
                            'stats',
                            'current_occupancy_duration',
                            'is_occupied_too_long',
                        ]
                    ]
                ]
            ]);
    }

    public function test_cannot_occupy_unavailable_table()
    {
        $table = Table::factory()->create([
            'store_id' => $this->store->id,
            'status' => 'occupied',
        ]);

        $response = $this->postJson("/api/v1/tables/{$table->id}/occupy", [
            'party_size' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'TABLE_NOT_AVAILABLE',
                ]
            ]);
    }

    public function test_can_get_available_tables()
    {
        // Create mix of available and occupied tables
        Table::factory()->create(['store_id' => $this->store->id, 'status' => 'available']);
        Table::factory()->create(['store_id' => $this->store->id, 'status' => 'available']);
        Table::factory()->create(['store_id' => $this->store->id, 'status' => 'occupied']);

        $response = $this->getJson('/api/v1/tables-available');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'table_number',
                        'status',
                        'is_available',
                    ]
                ],
                'meta' => [
                    'total_available',
                ]
            ]);

        $this->assertEquals(2, $response->json('meta.total_available'));
    }
}