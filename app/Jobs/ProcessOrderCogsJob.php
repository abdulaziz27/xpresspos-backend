<?php

namespace App\Jobs;

use App\Services\CogsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderCogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $orderId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(CogsService $cogsService): void
    {
        try {
            Log::info("Processing COGS for order {$this->orderId}");
            $cogsService->processOrderById($this->orderId);
            Log::info("COGS processing completed for order {$this->orderId}");
        } catch (\Exception $e) {
            Log::error("Failed to process COGS for order {$this->orderId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e; // Re-throw to mark job as failed
        }
    }
}

