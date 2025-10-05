<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncReliabilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSyncQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:process-queue 
                            {--batch-size=50 : Number of items to process in each batch}
                            {--max-batches=10 : Maximum number of batches to process}
                            {--store-id= : Process queue for specific store only}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending sync queue items';

    /**
     * Execute the console command.
     */
    public function handle(SyncReliabilityService $reliabilityService): int
    {
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $storeId = $this->option('store-id');

        $this->info("Starting sync queue processing...");
        $this->info("Batch size: {$batchSize}, Max batches: {$maxBatches}");
        
        if ($storeId) {
            $this->info("Processing for store: {$storeId}");
        }

        $totalProcessed = 0;
        $totalFailed = 0;
        $batchCount = 0;

        while ($batchCount < $maxBatches) {
            $this->info("Processing batch " . ($batchCount + 1) . "...");

            try {
                $result = $reliabilityService->processQueue($batchSize);

                $processed = $result['processed'];
                $failed = $result['failed'];
                $processingTime = $result['processing_time'];

                $totalProcessed += $processed;
                $totalFailed += $failed;
                $batchCount++;

                $this->info("Batch completed: {$processed} processed, {$failed} failed, {$processingTime}s");

                // If no items were processed, break the loop
                if ($processed === 0 && $failed === 0) {
                    $this->info("No more items in queue. Stopping.");
                    break;
                }

                // Small delay between batches to prevent overwhelming the system
                if ($batchCount < $maxBatches) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                $this->error("Error processing batch: " . $e->getMessage());
                Log::error('Sync queue processing error', [
                    'batch' => $batchCount + 1,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Continue with next batch after error
                $batchCount++;
                sleep(5); // Longer delay after error
            }
        }

        $this->info("Sync queue processing completed.");
        $this->info("Total processed: {$totalProcessed}");
        $this->info("Total failed: {$totalFailed}");
        $this->info("Batches processed: {$batchCount}");

        Log::info('Sync queue processing command completed', [
            'total_processed' => $totalProcessed,
            'total_failed' => $totalFailed,
            'batches_processed' => $batchCount,
            'store_id' => $storeId,
        ]);

        return Command::SUCCESS;
    }
}