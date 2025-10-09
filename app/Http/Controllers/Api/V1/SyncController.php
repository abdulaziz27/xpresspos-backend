<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SyncHistory;
use App\Models\SyncQueue;
use App\Services\Sync\SyncService;
use App\Services\Sync\ConflictResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    protected SyncService $syncService;
    protected ConflictResolver $conflictResolver;

    public function __construct(SyncService $syncService, ConflictResolver $conflictResolver)
    {
        $this->syncService = $syncService;
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * Batch sync multiple records.
     */
    public function batchSync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|string|max:255',
            'items' => 'required|array|min:1|max:100', // Limit batch size
            'items.*.idempotency_key' => 'required|string|max:255',
            'items.*.sync_type' => 'required|string|in:order,inventory,payment,product,member,expense',
            'items.*.operation' => 'required|string|in:create,update,delete',
            'items.*.entity_type' => 'required|string',
            'items.*.entity_id' => 'nullable|string',
            'items.*.data' => 'required|array',
            'items.*.timestamp' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $batchId = $request->input('batch_id');
        $items = $request->input('items');
        $results = [];
        $processedCount = 0;
        $conflictCount = 0;
        $errorCount = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $idempotencyKey = $item['idempotency_key'];

                // Check if already processed
                $existingSync = SyncHistory::findByIdempotencyKey($idempotencyKey);
                if ($existingSync) {
                    $results[] = [
                        'idempotency_key' => $idempotencyKey,
                        'status' => 'duplicate',
                        'message' => 'Already processed',
                        'entity_id' => $existingSync->entity_id,
                    ];
                    continue;
                }

                try {
                    // Process the sync item
                    $result = $this->syncService->processSync(
                        $idempotencyKey,
                        $item['sync_type'],
                        $item['operation'],
                        $item['entity_type'],
                        $item['data'],
                        $item['entity_id'] ?? null,
                        $item['timestamp']
                    );

                    $results[] = [
                        'idempotency_key' => $idempotencyKey,
                        'status' => $result['status'],
                        'message' => $result['message'],
                        'entity_id' => $result['entity_id'] ?? null,
                        'conflicts' => $result['conflicts'] ?? null,
                    ];

                    if ($result['status'] === 'completed') {
                        $processedCount++;
                    } elseif ($result['status'] === 'conflict') {
                        $conflictCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;

                    // Create failed sync record
                    SyncHistory::createSync(
                        $idempotencyKey,
                        $item['sync_type'],
                        $item['operation'],
                        $item['entity_type'],
                        $item['data'],
                        $item['entity_id'] ?? null
                    )->markFailed($e->getMessage());

                    $results[] = [
                        'idempotency_key' => $idempotencyKey,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batchId,
                    'total_items' => count($items),
                    'processed_count' => $processedCount,
                    'conflict_count' => $conflictCount,
                    'error_count' => $errorCount,
                    'results' => $results,
                ],
                'message' => 'Batch sync completed',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYNC_ERROR',
                    'message' => 'Batch sync failed: ' . $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * Get sync status for specific items.
     */
    public function getSyncStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idempotency_keys' => 'required|array|min:1|max:100',
            'idempotency_keys.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $keys = $request->input('idempotency_keys');
        $syncRecords = SyncHistory::whereIn('idempotency_key', $keys)
            ->where('store_id', request()->user()->store_id)
            ->get()
            ->keyBy('idempotency_key');

        $results = [];
        foreach ($keys as $key) {
            if (isset($syncRecords[$key])) {
                $sync = $syncRecords[$key];
                $results[] = [
                    'idempotency_key' => $key,
                    'status' => $sync->status,
                    'entity_id' => $sync->entity_id,
                    'conflicts' => $sync->conflicts,
                    'error_message' => $sync->error_message,
                    'retry_count' => $sync->retry_count,
                    'completed_at' => $sync->completed_at?->toISOString(),
                ];
            } else {
                $results[] = [
                    'idempotency_key' => $key,
                    'status' => 'not_found',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get sync stats - Alias for route compatibility.
     */
    public function getStats(Request $request): JsonResponse
    {
        return $this->getSyncStats($request);
    }

    /**
     * Get sync status - Alias for route compatibility.
     */
    public function getStatus(Request $request): JsonResponse
    {
        return $this->getSyncStatus($request);
    }

    /**
     * Get sync statistics for the store.
     */
    public function getSyncStats(Request $request): JsonResponse
    {
        $storeId = request()->user()->store_id;
        $period = $request->input('period', '24h'); // 24h, 7d, 30d

        $since = match ($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        $stats = SyncHistory::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->selectRaw('
                status,
                sync_type,
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_processing_time
            ')
            ->groupBy(['status', 'sync_type'])
            ->get();

        $summary = [
            'total' => $stats->sum('count'),
            'completed' => $stats->where('status', SyncHistory::STATUS_COMPLETED)->sum('count'),
            'pending' => $stats->where('status', SyncHistory::STATUS_PENDING)->sum('count'),
            'failed' => $stats->where('status', SyncHistory::STATUS_FAILED)->sum('count'),
            'conflicts' => $stats->where('status', SyncHistory::STATUS_CONFLICT)->sum('count'),
            'avg_processing_time' => $stats->where('status', SyncHistory::STATUS_COMPLETED)->avg('avg_processing_time'),
        ];

        $byType = $stats->groupBy('sync_type')->map(function ($typeStats) {
            return [
                'total' => $typeStats->sum('count'),
                'completed' => $typeStats->where('status', SyncHistory::STATUS_COMPLETED)->sum('count'),
                'pending' => $typeStats->where('status', SyncHistory::STATUS_PENDING)->sum('count'),
                'failed' => $typeStats->where('status', SyncHistory::STATUS_FAILED)->sum('count'),
                'conflicts' => $typeStats->where('status', SyncHistory::STATUS_CONFLICT)->sum('count'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'summary' => $summary,
                'by_type' => $byType,
            ],
        ]);
    }

    /**
     * Resolve sync conflicts.
     */
    public function resolveConflicts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conflicts' => 'required|array|min:1',
            'conflicts.*.idempotency_key' => 'required|string',
            'conflicts.*.resolution' => 'required|string|in:use_local,use_server,merge',
            'conflicts.*.merge_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $conflicts = $request->input('conflicts');
        $results = [];

        DB::beginTransaction();
        try {
            foreach ($conflicts as $conflict) {
                $syncRecord = SyncHistory::where('idempotency_key', $conflict['idempotency_key'])
                    ->where('store_id', request()->user()->store_id)
                    ->where('status', SyncHistory::STATUS_CONFLICT)
                    ->first();

                if (!$syncRecord) {
                    $results[] = [
                        'idempotency_key' => $conflict['idempotency_key'],
                        'status' => 'not_found',
                        'message' => 'Conflict record not found',
                    ];
                    continue;
                }

                try {
                    $result = $this->conflictResolver->resolveConflict(
                        $syncRecord,
                        $conflict['resolution'],
                        $conflict['merge_data'] ?? null
                    );

                    $results[] = [
                        'idempotency_key' => $conflict['idempotency_key'],
                        'status' => 'resolved',
                        'entity_id' => $result['entity_id'],
                        'message' => 'Conflict resolved successfully',
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'idempotency_key' => $conflict['idempotency_key'],
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'resolved_count' => collect($results)->where('status', 'resolved')->count(),
                    'results' => $results,
                ],
                'message' => 'Conflict resolution completed',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CONFLICT_RESOLUTION_ERROR',
                    'message' => 'Conflict resolution failed: ' . $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * Queue sync items for later processing.
     */
    public function queueSync(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'nullable|string|max:255',
            'items' => 'required|array|min:1|max:100',
            'items.*.sync_type' => 'required|string|in:order,inventory,payment,product,member,expense',
            'items.*.operation' => 'required|string|in:create,update,delete',
            'items.*.data' => 'required|array',
            'items.*.priority' => 'nullable|integer|min:0|max:15',
            'items.*.scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $batchId = $request->input('batch_id', Str::uuid()->toString());
        $items = $request->input('items');
        $queuedItems = [];

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $queueItem = SyncQueue::create([
                    'store_id' => request()->user()->store_id,
                    'batch_id' => $batchId,
                    'sync_type' => $item['sync_type'],
                    'operation' => $item['operation'],
                    'data' => $item['data'],
                    'priority' => $item['priority'] ?? SyncQueue::PRIORITY_NORMAL,
                    'scheduled_at' => isset($item['scheduled_at']) ?
                        \Carbon\Carbon::parse($item['scheduled_at']) : null,
                ]);

                $queuedItems[] = [
                    'id' => $queueItem->id,
                    'sync_type' => $queueItem->sync_type,
                    'operation' => $queueItem->operation,
                    'priority' => $queueItem->priority,
                    'scheduled_at' => $queueItem->scheduled_at?->toISOString(),
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batchId,
                    'queued_count' => count($queuedItems),
                    'items' => $queuedItems,
                ],
                'message' => 'Items queued for sync successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'QUEUE_ERROR',
                    'message' => 'Failed to queue sync items: ' . $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * Get queue status.
     */
    public function getQueueStatus(Request $request): JsonResponse
    {
        $storeId = request()->user()->store_id;
        $batchId = $request->input('batch_id');

        $query = SyncQueue::where('store_id', $storeId);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $stats = $query->selectRaw('
            status,
            COUNT(*) as count,
            MIN(created_at) as oldest_item,
            MAX(created_at) as newest_item
        ')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $summary = [
            'total' => $stats->sum('count'),
            'pending' => $stats->get(SyncQueue::STATUS_PENDING)?->count ?? 0,
            'processing' => $stats->get(SyncQueue::STATUS_PROCESSING)?->count ?? 0,
            'completed' => $stats->get(SyncQueue::STATUS_COMPLETED)?->count ?? 0,
            'failed' => $stats->get(SyncQueue::STATUS_FAILED)?->count ?? 0,
            'oldest_item' => $stats->min('oldest_item'),
            'newest_item' => $stats->max('newest_item'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'batch_id' => $batchId,
                'summary' => $summary,
                'by_status' => $stats->map(fn($stat) => [
                    'count' => $stat->count,
                    'oldest_item' => $stat->oldest_item,
                    'newest_item' => $stat->newest_item,
                ]),
            ],
        ]);
    }

    /**
     * Retry failed sync items.
     */
    public function retryFailed(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idempotency_keys' => 'nullable|array',
            'idempotency_keys.*' => 'string',
            'sync_type' => 'nullable|string|in:order,inventory,payment,product,member,expense',
            'max_retries' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        $storeId = request()->user()->store_id;
        $idempotencyKeys = $request->input('idempotency_keys');
        $syncType = $request->input('sync_type');
        $maxRetries = $request->input('max_retries', 3);

        $query = SyncHistory::where('store_id', $storeId)
            ->where('status', SyncHistory::STATUS_FAILED);

        if ($idempotencyKeys) {
            $query->whereIn('idempotency_key', $idempotencyKeys);
        }

        if ($syncType) {
            $query->where('sync_type', $syncType);
        }

        $failedSyncs = $query->where('retry_count', '<', $maxRetries)->get();
        $retriedCount = 0;

        foreach ($failedSyncs as $sync) {
            try {
                $result = $this->syncService->processSync(
                    $sync->idempotency_key,
                    $sync->sync_type,
                    $sync->operation,
                    $sync->entity_type,
                    $sync->payload,
                    $sync->entity_id
                );

                if ($result['status'] === 'completed') {
                    $retriedCount++;
                }
            } catch (\Exception $e) {
                $sync->markFailed($e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_failed' => $failedSyncs->count(),
                'retried_count' => $retriedCount,
            ],
            'message' => "Retried {$retriedCount} failed sync items",
        ]);
    }
}
