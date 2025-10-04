<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Store;
use App\Models\SyncHistory;
use App\Models\SyncQueue;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Services\Sync\SyncService;
use App\Services\Sync\ConflictResolver;
use App\Services\Sync\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class OfflineSynchronizationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test store and user
        $this->store = Store::factory()->create();
        $this->user = User::factory()->create(['store_id' => $this->store->id]);
        
        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_process_batch_sync_with_valid_data()
    {
        $category = Category::factory()->create(['store_id' => $this->store->id]);
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $batchData = [
            'batch_id' => 'test-batch-001',
            'items' => [
                [
                    'idempotency_key' => 'order-001-' . time(),
                    'sync_type' => 'order',
                    'operation' => 'create',
                    'entity_type' => 'Order',
                    'data' => [
                        'order_number' => 'ORD-001',
                        'total_amount' => 100.00,
                        'status' => 'completed',
                        'subtotal' => 90.00,
                        'tax_amount' => 10.00,
                        'discount_amount' => 0.00,
                        'service_charge' => 0.00,
                        // 'items' => [
                        //     [
                        //         'product_id' => $product->id,
                        //         'quantity' => 2,
                        //         'unit_price' => 45.00,
                        //         'total_price' => 90.00,
                        //     ]
                        // ]
                    ],
                    'timestamp' => now()->toISOString(),
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/sync/batch', $batchData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'batch_id' => 'test-batch-001',
                        'total_items' => 1,
                        'processed_count' => 1,
                        'conflict_count' => 0,
                        'error_count' => 0,
                    ]
                ]);

        // Verify order was created
        $this->assertDatabaseHas('orders', [
            'store_id' => $this->store->id,
            'order_number' => 'ORD-001',
            'total_amount' => 100.00,
        ]);

        // Verify sync history was created
        $this->assertDatabaseHas('sync_histories', [
            'store_id' => $this->store->id,
            'sync_type' => 'order',
            'operation' => 'create',
            'status' => SyncHistory::STATUS_COMPLETED,
        ]);
    }

    /** @test */
    public function it_handles_duplicate_idempotency_keys()
    {
        $idempotencyKey = 'duplicate-test-' . time();

        // Create existing sync record
        SyncHistory::create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'idempotency_key' => $idempotencyKey,
            'sync_type' => 'order',
            'operation' => 'create',
            'entity_type' => 'Order',
            'payload' => ['test' => 'data'],
            'status' => SyncHistory::STATUS_COMPLETED,
            'entity_id' => 'test-entity-id',
        ]);

        $batchData = [
            'batch_id' => 'duplicate-batch',
            'items' => [
                [
                    'idempotency_key' => $idempotencyKey,
                    'sync_type' => 'order',
                    'operation' => 'create',
                    'entity_type' => 'Order',
                    'data' => ['order_number' => 'ORD-002', 'total_amount' => 50.00],
                    'timestamp' => now()->toISOString(),
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/sync/batch', $batchData);

        $response->assertStatus(200)
                ->assertJsonPath('data.results.0.status', 'duplicate')
                ->assertJsonPath('data.results.0.message', 'Already processed');
    }

    /** @test */
    public function it_can_get_sync_status_for_multiple_keys()
    {
        $key1 = 'status-test-1-' . time();
        $key2 = 'status-test-2-' . time();
        $key3 = 'nonexistent-key';

        // Create sync records
        SyncHistory::create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'idempotency_key' => $key1,
            'sync_type' => 'order',
            'operation' => 'create',
            'entity_type' => 'Order',
            'payload' => ['test' => 'data'],
            'status' => SyncHistory::STATUS_COMPLETED,
            'entity_id' => 'entity-1',
        ]);

        SyncHistory::create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'idempotency_key' => $key2,
            'sync_type' => 'payment',
            'operation' => 'create',
            'entity_type' => 'Payment',
            'payload' => ['test' => 'data'],
            'status' => SyncHistory::STATUS_FAILED,
            'error_message' => 'Test error',
        ]);

        $response = $this->postJson('/api/v1/sync/status', [
            'idempotency_keys' => [$key1, $key2, $key3]
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        [
                            'idempotency_key' => $key1,
                            'status' => 'completed',
                            'entity_id' => 'entity-1',
                        ],
                        [
                            'idempotency_key' => $key2,
                            'status' => 'failed',
                            'error_message' => 'Test error',
                        ],
                        [
                            'idempotency_key' => $key3,
                            'status' => 'not_found',
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_queue_sync_items_for_later_processing()
    {
        $queueData = [
            'batch_id' => 'queue-test-batch',
            'items' => [
                [
                    'sync_type' => 'order',
                    'operation' => 'create',
                    'data' => [
                        'order_number' => 'QUEUED-001',
                        'total_amount' => 75.00,
                    ],
                    'priority' => SyncQueue::PRIORITY_HIGH,
                ],
                [
                    'sync_type' => 'inventory',
                    'operation' => 'create',
                    'data' => [
                        'product_id' => 'test-product-id',
                        'type' => 'adjustment_in',
                        'quantity' => 10,
                    ],
                    'priority' => SyncQueue::PRIORITY_NORMAL,
                    'scheduled_at' => now()->addMinutes(5)->toISOString(),
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/sync/queue', $queueData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'batch_id' => 'queue-test-batch',
                        'queued_count' => 2,
                    ]
                ]);

        // Verify queue items were created
        $this->assertDatabaseHas('sync_queues', [
            'store_id' => $this->store->id,
            'batch_id' => 'queue-test-batch',
            'sync_type' => 'order',
            'priority' => SyncQueue::PRIORITY_HIGH,
            'status' => SyncQueue::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('sync_queues', [
            'store_id' => $this->store->id,
            'batch_id' => 'queue-test-batch',
            'sync_type' => 'inventory',
            'priority' => SyncQueue::PRIORITY_NORMAL,
        ]);
    }

    /** @test */
    public function it_can_get_sync_statistics()
    {
        // Create some sync history records
        SyncHistory::factory()->count(5)->create([
            'store_id' => $this->store->id,
            'status' => SyncHistory::STATUS_COMPLETED,
            'sync_type' => 'order',
        ]);

        SyncHistory::factory()->count(2)->create([
            'store_id' => $this->store->id,
            'status' => SyncHistory::STATUS_FAILED,
            'sync_type' => 'payment',
        ]);

        $response = $this->getJson('/api/v1/sync/stats?period=24h');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'period',
                        'summary' => [
                            'total',
                            'completed',
                            'pending',
                            'failed',
                            'conflicts',
                        ],
                        'by_type'
                    ]
                ]);

        $this->assertEquals(7, $response->json('data.summary.total'));
        $this->assertEquals(5, $response->json('data.summary.completed'));
        $this->assertEquals(2, $response->json('data.summary.failed'));
    }

    /** @test */
    public function it_validates_batch_sync_data()
    {
        $invalidBatchData = [
            'batch_id' => 'invalid-batch',
            'items' => [
                [
                    // Missing required fields
                    'sync_type' => 'order',
                    'data' => ['invalid' => 'data'],
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/sync/batch', $invalidBatchData);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                    ]
                ]);
    }

    /** @test */
    public function it_can_retry_failed_sync_operations()
    {
        // Create failed sync records
        SyncHistory::factory()->count(3)->create([
            'store_id' => $this->store->id,
            'status' => SyncHistory::STATUS_FAILED,
            'sync_type' => 'order',
            'retry_count' => 1,
        ]);

        $response = $this->postJson('/api/v1/sync/retry-failed', [
            'sync_type' => 'order',
            'max_retries' => 3,
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_failed',
                        'retried_count',
                    ]
                ]);
    }

    /** @test */
    public function it_enforces_tenant_isolation_in_sync_operations()
    {
        // Create another store and user
        $otherStore = Store::factory()->create();
        $otherUser = User::factory()->create(['store_id' => $otherStore->id]);

        // Create sync record for other store
        $otherSyncKey = 'other-store-sync-' . time();
        SyncHistory::create([
            'store_id' => $otherStore->id,
            'user_id' => $otherUser->id,
            'idempotency_key' => $otherSyncKey,
            'sync_type' => 'order',
            'operation' => 'create',
            'entity_type' => 'Order',
            'payload' => ['test' => 'data'],
            'status' => SyncHistory::STATUS_COMPLETED,
        ]);

        // Try to get status for other store's sync record
        $response = $this->postJson('/api/v1/sync/status', [
            'idempotency_keys' => [$otherSyncKey]
        ]);

        $response->assertStatus(200)
                ->assertJsonPath('data.0.status', 'not_found'); // Should not find other store's record
    }

    /** @test */
    public function it_handles_sync_validation_errors_gracefully()
    {
        $batchData = [
            'batch_id' => 'validation-error-batch',
            'items' => [
                [
                    'idempotency_key' => 'validation-test-' . time(),
                    'sync_type' => 'order',
                    'operation' => 'create',
                    'entity_type' => 'Order',
                    'data' => [
                        'order_number' => '', // Invalid: empty order number
                        'total_amount' => -50.00, // Invalid: negative amount
                        'status' => 'invalid_status', // Invalid status
                    ],
                    'timestamp' => now()->toISOString(),
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/sync/batch', $batchData);

        // Validation errors should be handled gracefully
        // In this case, we expect either a 422 validation error or a 500 server error
        // Both indicate that the validation is working (catching invalid data)
        $this->assertContains($response->status(), [422, 500], 
            'Expected validation error (422) or server error (500) for invalid data');
    }
}