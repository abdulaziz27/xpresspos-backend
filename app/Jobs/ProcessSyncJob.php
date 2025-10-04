<?php

namespace App\Jobs;

use App\Models\SyncQueue;
use App\Services\Sync\SyncReliabilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $queueItemId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(string $queueItemId)
    {
        $this->queueItemId = $queueItemId;
    }

    /**
     * Execute the job.
     */
    public function handle(SyncReliabilityService $reliabilityService): void
    {
        $queueItem = SyncQueue::find($this->queueItemId);
        
        if (!$queueItem) {
            Log::warning('Sync queue item not found', ['queue_item_id' => $this->queueItemId]);
            return;
        }

        if ($queueItem->status !== SyncQueue::STATUS_PENDING) {
            Log::info('Sync queue item already processed', [
                'queue_item_id' => $this->queueItemId,
                'status' => $queueItem->status,
            ]);
            return;
        }

        Log::info('Processing sync queue item', [
            'queue_item_id' => $this->queueItemId,
            'sync_type' => $queueItem->sync_type,
            'operation' => $queueItem->operation,
        ]);

        try {
            $queueItem->markProcessing();

            // Generate idempotency key for queue item
            $idempotencyKey = "queue_{$queueItem->id}_" . md5(json_encode($queueItem->data));

            $result = $reliabilityService->processWithRetry(
                $idempotencyKey,
                $queueItem->sync_type,
                $queueItem->operation,
                'QueuedSync',
                $queueItem->data
            );

            if ($result['status'] === 'completed') {
                $queueItem->markCompleted();
                
                Log::info('Sync queue item processed successfully', [
                    'queue_item_id' => $this->queueItemId,
                    'entity_id' => $result['entity_id'] ?? null,
                ]);
            } else {
                $queueItem->markFailed('Sync processing failed: ' . ($result['message'] ?? 'Unknown error'));
                
                Log::warning('Sync queue item processing failed', [
                    'queue_item_id' => $this->queueItemId,
                    'result' => $result,
                ]);
            }

        } catch (\Exception $e) {
            $queueItem->markFailed($e->getMessage());
            
            Log::error('Sync queue item processing exception', [
                'queue_item_id' => $this->queueItemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger job retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $queueItem = SyncQueue::find($this->queueItemId);
        
        if ($queueItem) {
            $queueItem->markFailed('Job failed after all retries: ' . $exception->getMessage());
        }

        Log::critical('Sync queue job failed permanently', [
            'queue_item_id' => $this->queueItemId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $queueItem = SyncQueue::find($this->queueItemId);
        
        $tags = ['sync', 'queue-processing'];
        
        if ($queueItem) {
            $tags[] = "sync-type:{$queueItem->sync_type}";
            $tags[] = "operation:{$queueItem->operation}";
            $tags[] = "priority:{$queueItem->priority}";
        }

        return $tags;
    }
}