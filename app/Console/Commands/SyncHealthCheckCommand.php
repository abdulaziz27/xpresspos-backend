<?php

namespace App\Console\Commands;

use App\Services\Sync\SyncReliabilityService;
use App\Services\Sync\SyncPerformanceMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:health-check 
                            {--hours=24 : Number of hours to analyze}
                            {--store-id= : Check health for specific store only}
                            {--alert-threshold=10 : Failure rate threshold for alerts (percentage)}';

    /**
     * The console command description.
     */
    protected $description = 'Check sync system health and generate alerts';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncReliabilityService $reliabilityService,
        SyncPerformanceMonitor $performanceMonitor
    ): int {
        $hours = (int) $this->option('hours');
        $storeId = $this->option('store-id');
        $alertThreshold = (float) $this->option('alert-threshold');

        $this->info("Checking sync system health for the last {$hours} hours...");
        
        if ($storeId) {
            $this->info("Checking for store: {$storeId}");
        }

        try {
            // Get health metrics
            $healthMetrics = $reliabilityService->getHealthMetrics($storeId, $hours);
            
            // Display summary
            $this->displayHealthSummary($healthMetrics);
            
            // Get performance alerts
            $alerts = $performanceMonitor->getPerformanceAlerts();
            
            if (!empty($alerts)) {
                $this->displayAlerts($alerts, $alertThreshold);
            } else {
                $this->info("✅ No performance alerts detected.");
            }

            // Check for failed syncs that need recovery
            $recoveryStats = $reliabilityService->recoverFailedSyncs(null, $hours);
            
            if ($recoveryStats['total_failed'] > 0) {
                $this->displayRecoveryStats($recoveryStats);
            }

            // Log health check results
            Log::info('Sync health check completed', [
                'hours' => $hours,
                'store_id' => $storeId,
                'health_metrics' => $healthMetrics,
                'alerts_count' => count($alerts),
                'recovery_stats' => $recoveryStats,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Health check failed: " . $e->getMessage());
            
            Log::error('Sync health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Display health summary.
     */
    protected function displayHealthSummary(array $healthMetrics): void
    {
        $summary = $healthMetrics['summary'];
        
        $this->info("\n📊 Sync Health Summary:");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->line("Total Syncs: " . number_format($summary['total_syncs']));
        $this->line("Success Rate: " . $summary['success_rate'] . "%");
        $this->line("Avg Processing Time: " . $summary['avg_processing_time'] . "s");
        $this->line("Avg Retries: " . $summary['avg_retries']);
        $this->line("Max Retries: " . $summary['max_retries']);

        // Color-code success rate
        if ($summary['success_rate'] >= 95) {
            $this->info("✅ Success rate is healthy (≥95%)");
        } elseif ($summary['success_rate'] >= 90) {
            $this->comment("⚠️  Success rate needs attention (90-95%)");
        } else {
            $this->error("❌ Success rate is critical (<90%)");
        }

        // Display by status
        if (isset($healthMetrics['by_status'])) {
            $this->info("\n📈 By Status:");
            foreach ($healthMetrics['by_status'] as $status => $stats) {
                $this->line("  {$status}: " . number_format($stats['count']) . " operations");
            }
        }

        // Display by type
        if (isset($healthMetrics['by_type'])) {
            $this->info("\n📋 By Type:");
            foreach ($healthMetrics['by_type'] as $type => $stats) {
                $successRate = $stats['success_rate'];
                $icon = $successRate >= 95 ? '✅' : ($successRate >= 90 ? '⚠️' : '❌');
                $this->line("  {$icon} {$type}: {$successRate}% success rate ({$stats['total']} total)");
            }
        }
    }

    /**
     * Display performance alerts.
     */
    protected function displayAlerts(array $alerts, float $threshold): void
    {
        $this->error("\n🚨 Performance Alerts:");
        $this->error("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'warning';
            $icon = $severity === 'critical' ? '🔴' : '🟡';
            
            $this->line("{$icon} {$alert['type']} - {$alert['sync_type']}/{$alert['operation']}");
            
            if ($alert['type'] === 'high_failure_rate') {
                $this->line("   Failure Rate: {$alert['failure_rate']}% (threshold: {$alert['threshold']}%)");
                $this->line("   Failed: {$alert['failed_operations']}/{$alert['total_operations']} operations");
            } elseif ($alert['type'] === 'slow_operations') {
                $this->line("   Avg Time: {$alert['avg_processing_time']}s (threshold: {$alert['threshold']}s)");
                $this->line("   Total Operations: {$alert['total_operations']}");
            }
            
            $this->line("");
        }
    }

    /**
     * Display recovery statistics.
     */
    protected function displayRecoveryStats(array $recoveryStats): void
    {
        $this->info("\n🔄 Recovery Statistics:");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->line("Total Failed: " . $recoveryStats['total_failed']);
        $this->line("Recovered: " . $recoveryStats['recovered']);
        $this->line("Still Failed: " . $recoveryStats['still_failed']);
        
        if ($recoveryStats['recovered'] > 0) {
            $recoveryRate = round(($recoveryStats['recovered'] / $recoveryStats['total_failed']) * 100, 2);
            $this->info("✅ Recovery Rate: {$recoveryRate}%");
        }
        
        if ($recoveryStats['still_failed'] > 0) {
            $this->comment("⚠️  {$recoveryStats['still_failed']} syncs still require manual intervention");
        }
    }
}