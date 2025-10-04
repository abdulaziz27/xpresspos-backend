<?php

namespace App\Services\Sync;

use App\Models\SyncHistory;
use App\Models\SyncQueue;
use App\Jobs\ProcessSyncJob;
use App\Jobs\RetrySyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class SyncReliabilityService
{
    protected SyncService $syncService;
    protected SyncValidationService $validationService;
    protected SyncPerformanceMonitor $performanceMonitor;

    // Retry configuration
    protected array $retryConfig = [
        'max_retries' => 5,
        'base_delay' => 1, // seconds
        'max_delay' => 300, // 5 minutes
        'backoff_multiplier' => 2,
        'jitter_factor' => 0.1,
    ];

    public function __construct(
        SyncService $syncService,
        SyncValidationService $validationService,
        SyncPerformanceMonitor $performanceMonitor
    ) {
        $this->syncService = $syncService;
        $this->validationService = $validationService;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Process sync with retry mechanism and exponential backoff.
     */
    public function processWithRetry(
        string $idempotencyKey,
        string $syncType,
        string $operation,
        string $entityType,
        array $data,
        ?string $entityId = null,
        ?string $timestamp = null,
        int $attemptNumber = 1
    ): array {
        $startTime = microtime(true);
        
        try {
            // Validate data before processing
            $this->validationService->validateSyncData($syncType, $operation, $data);

            // Process the sync
            $result = $this->syncService->processSync(
                $idempotencyKey,
                $syncType,
                $operation,
                $entityType,
                $data,
                $entityId,
                $timestamp
            );

            // Record performance metrics
            $this->performanceMonitor->recordSuccess(
                $syncType,
                $operation,
                microtime(true) - $startTime,
                $attemptNumber
            );

            return $result;

        } catch (\Exception $e) {
            // Record failure metrics
            $this->performanceMonitor->recordFailure(
                $syncType,
                $operation,
                microtime(true) - $startTime,
                $attemptNumber,
                $e->getMessage()
            );

            // Determine if we should retry
            if ($this->shouldRetry($e, $attemptNumber)) {
                return $this->scheduleRetry(
                    $idempotencyKey,
                    $syncType,
                    $operation,
                    $entityType,
                    $data,
                    $entityId,
                    $timestamp,
                    $attemptNumber,
                    $e
                );
            }

            // Max retries reached or non-retryable error
            $this->handleFinalFailure($idempotencyKey, $syncType, $operation, $e, $attemptNumber);
            throw $e;
        }
    }

    /**
     * Determine if an error should trigger a retry.
     */
    protected function shouldRetry(\Exception $exception, int $attemptNumber): bool
    {
        // Don't retry if max attempts reached
        if ($attemptNumber >= $this->retryConfig['max_retries']) {
            return false;
        }

        // Don't retry validation errors or business logic errors
        if ($exception instanceof \InvalidArgumentException ||
            $exception instanceof \DomainException ||
            str_contains($exception->getMessage(), 'validation')) {
            return false;
        }

        // Retry for database connection issues, timeouts, etc.
        $retryableErrors = [
            'Connection refused',
            'Connection timed out',
            'Deadlock found',
            'Lock wait timeout',
            'Server has gone away',
            'Connection lost',
            'SQLSTATE[HY000]',
        ];

        $errorMessage = $exception->getMessage();
        foreach ($retryableErrors as $retryableError) {
            if (str_contains($errorMessage, $retryableError)) {
                return true;
            }
        }

        // Retry for HTTP 5xx errors if it's an API call
        if ($exception instanceof \GuzzleHttp\Exception\ServerException) {
            return true;
        }

        return false;
    }

    /**
     * Schedule a retry with exponential backoff.
     */
    protected function scheduleRetry(
        string $idempotencyKey,
        string $syncType,
        string $operation,
        string $entityType,
        array $data,
        ?string $entityId,
        ?string $timestamp,
        int $attemptNumber,
        \Exception $lastException
    ): array {
        $delay = $this->calculateRetryDelay($attemptNumber);
        
        // Update sync history with retry information
        $syncHistory = SyncHistory::findByIdempotencyKey($idempotencyKey);
        if ($syncHistory) {
            $syncHistory->update([
                'retry_count' => $attemptNumber,
                'last_retry_at' => now(),
                'error_message' => $lastException->getMessage(),
            ]);
        }

        // Schedule retry job
        RetrySyncJob::dispatch(
            $idempotencyKey,
            $syncType,
            $operation,
            $entityType,
            $data,
            $entityId,
            $timestamp,
            $attemptNumber + 1
        )->delay(now()->addSeconds($delay));

        Log::info('Sync retry scheduled', [
            'idempotency_key' => $idempotencyKey,
            'sync_type' => $syncType,
            'attempt_number' => $attemptNumber,
            'next_attempt' => $attemptNumber + 1,
            'delay_seconds' => $delay,
            'error' => $lastException->getMessage(),
        ]);

        return [
            'status' => 'retry_scheduled',
            'message' => "Retry scheduled for attempt {$attemptNumber + 1}",
            'retry_delay' => $delay,
            'next_attempt_at' => now()->addSeconds($delay)->toISOString(),
        ];
    }

    /**
     * Calculate retry delay with exponential backoff and jitter.
     */
    protected function calculateRetryDelay(int $attemptNumber): int
    {
        $baseDelay = $this->retryConfig['base_delay'];
        $multiplier = $this->retryConfig['backoff_multiplier'];
        $maxDelay = $this->retryConfig['max_delay'];
        $jitterFactor = $this->retryConfig['jitter_factor'];

        // Exponential backoff: delay = base_delay * (multiplier ^ (attempt - 1))
        $delay = $baseDelay * pow($multiplier, $attemptNumber - 1);
        
        // Cap at max delay
        $delay = min($delay, $maxDelay);
        
        // Add jitter to prevent thundering herd
        $jitter = $delay * $jitterFactor * (mt_rand() / mt_getrandmax() - 0.5);
        $delay = max(1, $delay + $jitter);

        return (int) $delay;
    }

    /**
     * Handle final failure after all retries exhausted.
     */
    protected function handleFinalFailure(
        string $idempotencyKey,
        string $syncType,
        string $operation,
        \Exception $exception,
        int $attemptNumber
    ): void {
        $syncHistory = SyncHistory::findByIdempotencyKey($idempotencyKey);
        if ($syncHistory) {
            $syncHistory->markFailed($exception->getMessage());
        }

        // Log critical failure
        Log::critical('Sync failed after all retries', [
            'idempotency_key' => $idempotencyKey,
            'sync_type' => $syncType,
            'operation' => $operation,
            'total_attempts' => $attemptNumber,
            'final_error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Record failure metrics
        $this->performanceMonitor->recordFinalFailure($syncType, $operation, $attemptNumber);

        // Trigger alerts if needed
        $this->triggerFailureAlert($syncType, $operation, $exception, $attemptNumber);
    }

    /**
     * Trigger failure alert for critical sync failures.
     */
    protected function triggerFailureAlert(
        string $syncType,
        string $operation,
        \Exception $exception,
        int $attemptNumber
    ): void {
        // Check if this type of failure should trigger an alert
        $criticalSyncTypes = ['order', 'payment', 'inventory'];
        
        if (in_array($syncType, $criticalSyncTypes)) {
            // In a real implementation, you would send alerts via:
            // - Email notifications
            // - Slack/Discord webhooks
            // - SMS alerts
            // - Push notifications to admin app
            
            Log::alert('Critical sync failure detected', [
                'sync_type' => $syncType,
                'operation' => $operation,
                'error' => $exception->getMessage(),
                'attempts' => $attemptNumber,
                'requires_manual_intervention' => true,
            ]);
        }
    }

    /**
     * Recover failed sync operations.
     */
    public function recoverFailedSyncs(?string $syncType = null, int $maxAge = 24): array
    {
        $cutoffTime = now()->subHours($maxAge);
        
        $query = SyncHistory::where('status', SyncHistory::STATUS_FAILED)
            ->where('created_at', '>=', $cutoffTime)
            ->where('retry_count', '<', $this->retryConfig['max_retries']);

        if ($syncType) {
            $query->where('sync_type', $syncType);
        }

        $failedSyncs = $query->get();
        $recoveredCount = 0;
        $stillFailedCount = 0;

        foreach ($failedSyncs as $sync) {
            try {
                // Reset status to pending for retry
                $sync->update([
                    'status' => SyncHistory::STATUS_PENDING,
                    'error_message' => null,
                ]);

                // Attempt recovery
                $result = $this->processWithRetry(
                    $sync->idempotency_key,
                    $sync->sync_type,
                    $sync->operation,
                    $sync->entity_type,
                    $sync->payload,
                    $sync->entity_id
                );

                if ($result['status'] === 'completed') {
                    $recoveredCount++;
                }

            } catch (\Exception $e) {
                $stillFailedCount++;
                Log::warning('Failed to recover sync operation', [
                    'idempotency_key' => $sync->idempotency_key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Sync recovery completed', [
            'total_failed' => $failedSyncs->count(),
            'recovered' => $recoveredCount,
            'still_failed' => $stillFailedCount,
            'sync_type' => $syncType,
            'max_age_hours' => $maxAge,
        ]);

        return [
            'total_failed' => $failedSyncs->count(),
            'recovered' => $recoveredCount,
            'still_failed' => $stillFailedCount,
        ];
    }

    /**
     * Process sync queue with reliability features.
     */
    public function processQueue(int $batchSize = 50): array
    {
        $startTime = microtime(true);
        $processedCount = 0;
        $failedCount = 0;

        // Get ready queue items ordered by priority
        $queueItems = SyncQueue::ready()
            ->byPriority()
            ->limit($batchSize)
            ->get();

        if ($queueItems->isEmpty()) {
            return [
                'processed' => 0,
                'failed' => 0,
                'processing_time' => 0,
                'message' => 'No items in queue',
            ];
        }

        DB::beginTransaction();
        try {
            foreach ($queueItems as $item) {
                $item->markProcessing();
                
                try {
                    // Generate idempotency key for queue item
                    $idempotencyKey = "queue_{$item->id}_" . md5(json_encode($item->data));
                    
                    $result = $this->processWithRetry(
                        $idempotencyKey,
                        $item->sync_type,
                        $item->operation,
                        'QueuedSync', // Generic entity type for queued items
                        $item->data
                    );

                    if ($result['status'] === 'completed') {
                        $item->markCompleted();
                        $processedCount++;
                    } else {
                        $item->markFailed('Sync processing failed');
                        $failedCount++;
                    }

                } catch (\Exception $e) {
                    $item->markFailed($e->getMessage());
                    $failedCount++;
                    
                    Log::error('Queue item processing failed', [
                        'queue_item_id' => $item->id,
                        'sync_type' => $item->sync_type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $processingTime = microtime(true) - $startTime;

        Log::info('Queue processing completed', [
            'batch_size' => $batchSize,
            'processed' => $processedCount,
            'failed' => $failedCount,
            'processing_time' => $processingTime,
        ]);

        return [
            'processed' => $processedCount,
            'failed' => $failedCount,
            'processing_time' => round($processingTime, 3),
            'message' => "Processed {$processedCount} items, {$failedCount} failed",
        ];
    }

    /**
     * Get sync health metrics.
     */
    public function getHealthMetrics(?string $storeId = null, int $hours = 24): array
    {
        $storeId = $storeId ?? auth()->user()->store_id;
        $since = now()->subHours($hours);

        $metrics = SyncHistory::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->selectRaw('
                status,
                sync_type,
                COUNT(*) as count,
                AVG(retry_count) as avg_retries,
                MAX(retry_count) as max_retries,
                AVG(TIMESTAMPDIFF(SECOND, created_at, COALESCE(completed_at, updated_at))) as avg_processing_time
            ')
            ->groupBy(['status', 'sync_type'])
            ->get();

        $summary = [
            'total_syncs' => $metrics->sum('count'),
            'success_rate' => 0,
            'avg_processing_time' => 0,
            'avg_retries' => 0,
            'max_retries' => 0,
        ];

        $completedCount = $metrics->where('status', SyncHistory::STATUS_COMPLETED)->sum('count');
        if ($summary['total_syncs'] > 0) {
            $summary['success_rate'] = round(($completedCount / $summary['total_syncs']) * 100, 2);
        }

        $completedMetrics = $metrics->where('status', SyncHistory::STATUS_COMPLETED);
        if ($completedMetrics->isNotEmpty()) {
            $summary['avg_processing_time'] = round($completedMetrics->avg('avg_processing_time'), 2);
        }

        if ($metrics->isNotEmpty()) {
            $summary['avg_retries'] = round($metrics->avg('avg_retries'), 2);
            $summary['max_retries'] = $metrics->max('max_retries');
        }

        $byStatus = $metrics->groupBy('status')->map(function ($statusMetrics) {
            return [
                'count' => $statusMetrics->sum('count'),
                'avg_retries' => round($statusMetrics->avg('avg_retries'), 2),
                'avg_processing_time' => round($statusMetrics->avg('avg_processing_time'), 2),
            ];
        });

        $byType = $metrics->groupBy('sync_type')->map(function ($typeMetrics) {
            $total = $typeMetrics->sum('count');
            $completed = $typeMetrics->where('status', SyncHistory::STATUS_COMPLETED)->sum('count');
            
            return [
                'total' => $total,
                'completed' => $completed,
                'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'avg_retries' => round($typeMetrics->avg('avg_retries'), 2),
            ];
        });

        return [
            'period_hours' => $hours,
            'summary' => $summary,
            'by_status' => $byStatus,
            'by_type' => $byType,
        ];
    }

    /**
     * Clean up old sync records and optimize performance.
     */
    public function cleanup(int $daysOld = 30): array
    {
        $cutoffDate = now()->subDays($daysOld);
        
        // Clean up completed sync histories
        $deletedSyncHistories = SyncHistory::where('created_at', '<', $cutoffDate)
            ->where('status', SyncHistory::STATUS_COMPLETED)
            ->delete();

        // Clean up completed queue items
        $deletedQueueItems = SyncQueue::where('created_at', '<', $cutoffDate)
            ->where('status', SyncQueue::STATUS_COMPLETED)
            ->delete();

        // Archive old failed records instead of deleting them
        $archivedFailures = SyncHistory::where('created_at', '<', $cutoffDate)
            ->whereIn('status', [SyncHistory::STATUS_FAILED, SyncHistory::STATUS_CONFLICT])
            ->update(['status' => 'archived']);

        Log::info('Sync cleanup completed', [
            'cutoff_date' => $cutoffDate,
            'deleted_sync_histories' => $deletedSyncHistories,
            'deleted_queue_items' => $deletedQueueItems,
            'archived_failures' => $archivedFailures,
        ]);

        return [
            'deleted_sync_histories' => $deletedSyncHistories,
            'deleted_queue_items' => $deletedQueueItems,
            'archived_failures' => $archivedFailures,
        ];
    }
}