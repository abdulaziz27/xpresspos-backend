<?php

namespace App\Jobs;

use App\Services\Sync\SyncReliabilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetrySyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $idempotencyKey;
    protected string $syncType;
    protected string $operation;
    protected string $entityType;
    protected array $data;
    protected ?string $entityId;
    protected ?string $timestamp;
    protected int $attemptNumber;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1; // We handle retries manually

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $idempotencyKey,
        string $syncType,
        string $operation,
        string $entityType,
        array $data,
        ?string $entityId = null,
        ?string $timestamp = null,
        int $attemptNumber = 1
    ) {
        $this->idempotencyKey = $idempotencyKey;
        $this->syncType = $syncType;
        $this->operation = $operation;
        $this->entityType = $entityType;
        $this->data = $data;
        $this->entityId = $entityId;
        $this->timestamp = $timestamp;
        $this->attemptNumber = $attemptNumber;

        // Set queue based on sync type priority
        $this->onQueue($this->getQueueName($syncType));
    }

    /**
     * Execute the job.
     */
    public function handle(SyncReliabilityService $reliabilityService): void
    {
        Log::info('Retry sync job started', [
            'idempotency_key' => $this->idempotencyKey,
            'sync_type' => $this->syncType,
            'operation' => $this->operation,
            'attempt_number' => $this->attemptNumber,
        ]);

        try {
            $result = $reliabilityService->processWithRetry(
                $this->idempotencyKey,
                $this->syncType,
                $this->operation,
                $this->entityType,
                $this->data,
                $this->entityId,
                $this->timestamp,
                $this->attemptNumber
            );

            Log::info('Retry sync job completed', [
                'idempotency_key' => $this->idempotencyKey,
                'result_status' => $result['status'],
                'attempt_number' => $this->attemptNumber,
            ]);

        } catch (\Exception $e) {
            Log::error('Retry sync job failed', [
                'idempotency_key' => $this->idempotencyKey,
                'sync_type' => $this->syncType,
                'operation' => $this->operation,
                'attempt_number' => $this->attemptNumber,
                'error' => $e->getMessage(),
            ]);

            // Don't rethrow - we handle retries in the service
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Retry sync job failed permanently', [
            'idempotency_key' => $this->idempotencyKey,
            'sync_type' => $this->syncType,
            'operation' => $this->operation,
            'attempt_number' => $this->attemptNumber,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get queue name based on sync type.
     */
    protected function getQueueName(string $syncType): string
    {
        return match ($syncType) {
            'order', 'payment' => 'sync-high-priority',
            'inventory' => 'sync-medium-priority',
            default => 'sync-low-priority',
        };
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'sync',
            'retry',
            "sync-type:{$this->syncType}",
            "operation:{$this->operation}",
            "attempt:{$this->attemptNumber}",
        ];
    }
}