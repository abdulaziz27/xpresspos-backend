<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncReliabilityService;
use App\Services\Sync\IdempotencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:cleanup 
                            {--days=30 : Age in days for records to be cleaned up}
                            {--dry-run : Show what would be cleaned up without actually doing it}
                            {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old sync records and optimize performance';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncReliabilityService $reliabilityService,
        IdempotencyService $idempotencyService
    ): int {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Sync cleanup process starting...");
        $this->info("Cleaning up records older than {$days} days");
        
        if ($dryRun) {
            $this->comment("🔍 DRY RUN MODE - No actual changes will be made");
        }

        try {
            if (!$force && !$dryRun) {
                if (!$this->confirm('This will permanently delete old sync records. Continue?')) {
                    $this->info('Cleanup cancelled.');
                    return Command::SUCCESS;
                }
            }

            if ($dryRun) {
                $this->performDryRun($reliabilityService, $idempotencyService, $days);
            } else {
                $this->performCleanup($reliabilityService, $idempotencyService, $days);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cleanup failed: " . $e->getMessage());
            
            Log::error('Sync cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Perform dry run to show what would be cleaned up.
     */
    protected function performDryRun(
        SyncReliabilityService $reliabilityService,
        IdempotencyService $idempotencyService,
        int $days
    ): void {
        $this->info("\n🔍 Dry Run Results:");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Get statistics about what would be cleaned
        $cutoffDate = now()->subDays($days);
        
        // Count sync histories that would be deleted
        $completedSyncCount = \App\Models\SyncHistory::where('created_at', '<', $cutoffDate)
            ->where('status', \App\Models\SyncHistory::STATUS_COMPLETED)
            ->count();

        // Count queue items that would be deleted
        $completedQueueCount = \App\Models\SyncQueue::where('created_at', '<', $cutoffDate)
            ->where('status', \App\Models\SyncQueue::STATUS_COMPLETED)
            ->count();

        // Count failed records that would be archived
        $failedCount = \App\Models\SyncHistory::where('created_at', '<', $cutoffDate)
            ->whereIn('status', [\App\Models\SyncHistory::STATUS_FAILED, \App\Models\SyncHistory::STATUS_CONFLICT])
            ->count();

        $this->line("Completed sync histories to delete: " . number_format($completedSyncCount));
        $this->line("Completed queue items to delete: " . number_format($completedQueueCount));
        $this->line("Failed records to archive: " . number_format($failedCount));

        $totalRecords = $completedSyncCount + $completedQueueCount;
        $this->info("\nTotal records that would be affected: " . number_format($totalRecords + $failedCount));

        // Show disk space that would be freed (rough estimate)
        $estimatedSpaceMB = ($totalRecords * 2) / 1024; // Rough estimate: 2KB per record
        $this->comment("Estimated disk space to be freed: ~" . round($estimatedSpaceMB, 2) . " MB");

        // Get idempotency statistics
        $idempotencyStats = $idempotencyService->getStats();
        $this->info("\n📊 Idempotency Statistics:");
        $this->line("Total requests: " . number_format($idempotencyStats['total_requests']));
        $this->line("Unique keys: " . number_format($idempotencyStats['unique_keys']));
        $this->line("Duplicate requests: " . number_format($idempotencyStats['duplicate_requests']));
        $this->line("Duplicate rate: " . $idempotencyStats['duplicate_rate'] . "%");
    }

    /**
     * Perform actual cleanup.
     */
    protected function performCleanup(
        SyncReliabilityService $reliabilityService,
        IdempotencyService $idempotencyService,
        int $days
    ): void {
        $this->info("\n🧹 Starting cleanup process...");

        // Perform cleanup
        $startTime = microtime(true);
        $results = $reliabilityService->cleanup($days);
        $processingTime = microtime(true) - $startTime;

        // Clean up idempotency records
        $idempotencyCleanedCount = $idempotencyService->cleanup($days);

        // Display results
        $this->info("\n✅ Cleanup completed successfully!");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->line("Deleted sync histories: " . number_format($results['deleted_sync_histories']));
        $this->line("Deleted queue items: " . number_format($results['deleted_queue_items']));
        $this->line("Archived failed records: " . number_format($results['archived_failures']));
        $this->line("Cleaned idempotency records: " . number_format($idempotencyCleanedCount));
        
        $totalCleaned = $results['deleted_sync_histories'] + $results['deleted_queue_items'] + $idempotencyCleanedCount;
        $this->info("Total records processed: " . number_format($totalCleaned));
        $this->info("Processing time: " . round($processingTime, 2) . " seconds");

        // Estimate freed space
        $estimatedSpaceMB = ($totalCleaned * 2) / 1024; // Rough estimate
        $this->comment("Estimated disk space freed: ~" . round($estimatedSpaceMB, 2) . " MB");

        // Log cleanup results
        Log::info('Sync cleanup completed', [
            'days' => $days,
            'results' => $results,
            'idempotency_cleaned' => $idempotencyCleanedCount,
            'total_cleaned' => $totalCleaned,
            'processing_time' => $processingTime,
        ]);

        $this->info("\n💡 Recommendations:");
        
        if ($results['archived_failures'] > 100) {
            $this->comment("• Consider investigating recurring failure patterns");
        }
        
        if ($totalCleaned > 10000) {
            $this->comment("• Consider running cleanup more frequently");
        }
        
        $this->comment("• Monitor sync performance after cleanup");
        $this->comment("• Run 'sync:health-check' to verify system health");
    }
}