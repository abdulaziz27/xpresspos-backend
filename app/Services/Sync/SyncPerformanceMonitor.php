<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncPerformanceMonitor
{
    protected string $cachePrefix = 'sync_perf';
    protected int $cacheTtl = 3600; // 1 hour

    /**
     * Record successful sync operation.
     */
    public function recordSuccess(
        string $syncType,
        string $operation,
        float $processingTime,
        int $attemptNumber = 1
    ): void {
        $this->recordMetric('success', $syncType, $operation, $processingTime, $attemptNumber);
        
        // Update success rate cache
        $this->updateSuccessRate($syncType, $operation, true);
        
        // Log slow operations
        if ($processingTime > 5.0) { // 5 seconds threshold
            Log::warning('Slow sync operation detected', [
                'sync_type' => $syncType,
                'operation' => $operation,
                'processing_time' => $processingTime,
                'attempt_number' => $attemptNumber,
            ]);
        }
    }

    /**
     * Record failed sync operation.
     */
    public function recordFailure(
        string $syncType,
        string $operation,
        float $processingTime,
        int $attemptNumber,
        string $errorMessage
    ): void {
        $this->recordMetric('failure', $syncType, $operation, $processingTime, $attemptNumber, $errorMessage);
        
        // Update success rate cache
        $this->updateSuccessRate($syncType, $operation, false);
        
        // Track error patterns
        $this->trackErrorPattern($syncType, $operation, $errorMessage);
    }

    /**
     * Record final failure after all retries.
     */
    public function recordFinalFailure(string $syncType, string $operation, int $totalAttempts): void
    {
        $key = $this->getCacheKey('final_failures', $syncType, $operation);
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, $this->cacheTtl);

        // Track retry statistics
        $retryKey = $this->getCacheKey('retry_stats', $syncType, $operation);
        $retryStats = Cache::get($retryKey, ['total' => 0, 'sum_attempts' => 0]);
        $retryStats['total']++;
        $retryStats['sum_attempts'] += $totalAttempts;
        Cache::put($retryKey, $retryStats, $this->cacheTtl);

        Log::error('Final sync failure recorded', [
            'sync_type' => $syncType,
            'operation' => $operation,
            'total_attempts' => $totalAttempts,
        ]);
    }

    /**
     * Record generic metric.
     */
    protected function recordMetric(
        string $result,
        string $syncType,
        string $operation,
        float $processingTime,
        int $attemptNumber,
        ?string $errorMessage = null
    ): void {
        $timestamp = now();
        $hour = $timestamp->format('Y-m-d-H');
        
        // Record hourly metrics
        $hourlyKey = $this->getCacheKey('hourly', $syncType, $operation, $hour);
        $hourlyData = Cache::get($hourlyKey, [
            'total' => 0,
            'success' => 0,
            'failure' => 0,
            'total_time' => 0,
            'min_time' => null,
            'max_time' => null,
            'total_attempts' => 0,
        ]);

        $hourlyData['total']++;
        $hourlyData[$result]++;
        $hourlyData['total_time'] += $processingTime;
        $hourlyData['total_attempts'] += $attemptNumber;
        
        if ($hourlyData['min_time'] === null || $processingTime < $hourlyData['min_time']) {
            $hourlyData['min_time'] = $processingTime;
        }
        if ($hourlyData['max_time'] === null || $processingTime > $hourlyData['max_time']) {
            $hourlyData['max_time'] = $processingTime;
        }

        Cache::put($hourlyKey, $hourlyData, $this->cacheTtl);

        // Record daily aggregates
        $dailyKey = $this->getCacheKey('daily', $syncType, $operation, $timestamp->format('Y-m-d'));
        $dailyData = Cache::get($dailyKey, [
            'total' => 0,
            'success' => 0,
            'failure' => 0,
            'total_time' => 0,
            'total_attempts' => 0,
        ]);

        $dailyData['total']++;
        $dailyData[$result]++;
        $dailyData['total_time'] += $processingTime;
        $dailyData['total_attempts'] += $attemptNumber;

        Cache::put($dailyKey, $dailyData, 86400); // 24 hours

        // Log detailed metrics for analysis
        Log::info('Sync performance metric recorded', [
            'result' => $result,
            'sync_type' => $syncType,
            'operation' => $operation,
            'processing_time' => $processingTime,
            'attempt_number' => $attemptNumber,
            'error_message' => $errorMessage,
            'timestamp' => $timestamp->toISOString(),
        ]);
    }

    /**
     * Update success rate cache.
     */
    protected function updateSuccessRate(string $syncType, string $operation, bool $success): void
    {
        $key = $this->getCacheKey('success_rate', $syncType, $operation);
        $data = Cache::get($key, ['total' => 0, 'success' => 0]);
        
        $data['total']++;
        if ($success) {
            $data['success']++;
        }

        // Keep only recent data (sliding window)
        if ($data['total'] > 1000) {
            $data['total'] = 900;
            $data['success'] = (int) ($data['success'] * 0.9);
        }

        Cache::put($key, $data, $this->cacheTtl);
    }

    /**
     * Track error patterns.
     */
    protected function trackErrorPattern(string $syncType, string $operation, string $errorMessage): void
    {
        // Categorize error
        $errorCategory = $this->categorizeError($errorMessage);
        
        $key = $this->getCacheKey('error_patterns', $syncType, $operation, $errorCategory);
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, $this->cacheTtl);

        // Track specific error messages
        $errorHash = md5($errorMessage);
        $errorKey = $this->getCacheKey('specific_errors', $syncType, $operation, $errorHash);
        $errorData = Cache::get($errorKey, ['count' => 0, 'message' => $errorMessage, 'first_seen' => now()]);
        $errorData['count']++;
        $errorData['last_seen'] = now();
        Cache::put($errorKey, $errorData, $this->cacheTtl);
    }

    /**
     * Categorize error message.
     */
    protected function categorizeError(string $errorMessage): string
    {
        $errorMessage = strtolower($errorMessage);

        if (str_contains($errorMessage, 'connection') || str_contains($errorMessage, 'timeout')) {
            return 'connection';
        }
        if (str_contains($errorMessage, 'validation') || str_contains($errorMessage, 'invalid')) {
            return 'validation';
        }
        if (str_contains($errorMessage, 'not found') || str_contains($errorMessage, 'missing')) {
            return 'not_found';
        }
        if (str_contains($errorMessage, 'duplicate') || str_contains($errorMessage, 'already exists')) {
            return 'duplicate';
        }
        if (str_contains($errorMessage, 'permission') || str_contains($errorMessage, 'unauthorized')) {
            return 'permission';
        }
        if (str_contains($errorMessage, 'deadlock') || str_contains($errorMessage, 'lock')) {
            return 'database_lock';
        }

        return 'other';
    }

    /**
     * Get performance metrics.
     */
    public function getMetrics(?string $syncType = null, ?string $operation = null, int $hours = 24): array
    {
        $metrics = [
            'summary' => $this->getSummaryMetrics($syncType, $operation, $hours),
            'hourly' => $this->getHourlyMetrics($syncType, $operation, $hours),
            'error_patterns' => $this->getErrorPatterns($syncType, $operation),
            'performance_trends' => $this->getPerformanceTrends($syncType, $operation, $hours),
        ];

        return $metrics;
    }

    /**
     * Get summary metrics.
     */
    protected function getSummaryMetrics(?string $syncType, ?string $operation, int $hours): array
    {
        $summary = [
            'total_operations' => 0,
            'successful_operations' => 0,
            'failed_operations' => 0,
            'success_rate' => 0,
            'avg_processing_time' => 0,
            'avg_attempts_per_operation' => 0,
            'total_final_failures' => 0,
        ];

        // Get data from cache for the specified period
        $endTime = now();
        $startTime = $endTime->copy()->subHours($hours);

        for ($time = $startTime->copy(); $time <= $endTime; $time->addHour()) {
            $hour = $time->format('Y-m-d-H');
            $key = $this->getCacheKey('hourly', $syncType ?? '*', $operation ?? '*', $hour);
            
            if ($syncType && $operation) {
                $data = Cache::get($key, []);
                if (!empty($data)) {
                    $summary['total_operations'] += $data['total'] ?? 0;
                    $summary['successful_operations'] += $data['success'] ?? 0;
                    $summary['failed_operations'] += $data['failure'] ?? 0;
                    $summary['avg_processing_time'] += $data['total_time'] ?? 0;
                    $summary['avg_attempts_per_operation'] += $data['total_attempts'] ?? 0;
                }
            } else {
                // Aggregate across all types/operations - simplified for demo
                // In production, you'd iterate through all cached keys
            }
        }

        if ($summary['total_operations'] > 0) {
            $summary['success_rate'] = round(
                ($summary['successful_operations'] / $summary['total_operations']) * 100, 
                2
            );
            $summary['avg_processing_time'] = round(
                $summary['avg_processing_time'] / $summary['total_operations'], 
                3
            );
            $summary['avg_attempts_per_operation'] = round(
                $summary['avg_attempts_per_operation'] / $summary['total_operations'], 
                2
            );
        }

        return $summary;
    }

    /**
     * Get hourly metrics.
     */
    protected function getHourlyMetrics(?string $syncType, ?string $operation, int $hours): array
    {
        $hourlyMetrics = [];
        $endTime = now();
        $startTime = $endTime->copy()->subHours($hours);

        for ($time = $startTime->copy(); $time <= $endTime; $time->addHour()) {
            $hour = $time->format('Y-m-d-H');
            $key = $this->getCacheKey('hourly', $syncType ?? '*', $operation ?? '*', $hour);
            
            $data = Cache::get($key, [
                'total' => 0,
                'success' => 0,
                'failure' => 0,
                'total_time' => 0,
                'min_time' => null,
                'max_time' => null,
            ]);

            $hourlyMetrics[] = [
                'hour' => $hour,
                'total' => $data['total'],
                'success' => $data['success'],
                'failure' => $data['failure'],
                'success_rate' => $data['total'] > 0 ? 
                    round(($data['success'] / $data['total']) * 100, 2) : 0,
                'avg_processing_time' => $data['total'] > 0 ? 
                    round($data['total_time'] / $data['total'], 3) : 0,
                'min_processing_time' => $data['min_time'],
                'max_processing_time' => $data['max_time'],
            ];
        }

        return $hourlyMetrics;
    }

    /**
     * Get error patterns.
     */
    protected function getErrorPatterns(?string $syncType, ?string $operation): array
    {
        $patterns = [];
        $categories = ['connection', 'validation', 'not_found', 'duplicate', 'permission', 'database_lock', 'other'];

        foreach ($categories as $category) {
            $key = $this->getCacheKey('error_patterns', $syncType ?? '*', $operation ?? '*', $category);
            $count = Cache::get($key, 0);
            
            if ($count > 0) {
                $patterns[$category] = $count;
            }
        }

        return $patterns;
    }

    /**
     * Get performance trends.
     */
    protected function getPerformanceTrends(?string $syncType, ?string $operation, int $hours): array
    {
        $trends = [
            'processing_time_trend' => [],
            'success_rate_trend' => [],
            'volume_trend' => [],
        ];

        $endTime = now();
        $startTime = $endTime->copy()->subHours($hours);
        $interval = max(1, $hours / 24); // Show up to 24 data points

        for ($time = $startTime->copy(); $time <= $endTime; $time->addHours($interval)) {
            $hour = $time->format('Y-m-d-H');
            $key = $this->getCacheKey('hourly', $syncType ?? '*', $operation ?? '*', $hour);
            
            $data = Cache::get($key, [
                'total' => 0,
                'success' => 0,
                'total_time' => 0,
            ]);

            $trends['volume_trend'][] = [
                'time' => $time->toISOString(),
                'value' => $data['total'],
            ];

            $trends['success_rate_trend'][] = [
                'time' => $time->toISOString(),
                'value' => $data['total'] > 0 ? 
                    round(($data['success'] / $data['total']) * 100, 2) : 0,
            ];

            $trends['processing_time_trend'][] = [
                'time' => $time->toISOString(),
                'value' => $data['total'] > 0 ? 
                    round($data['total_time'] / $data['total'], 3) : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get cache key.
     */
    protected function getCacheKey(string $type, string $syncType, string $operation, ?string $suffix = null): string
    {
        $key = "{$this->cachePrefix}:{$type}:{$syncType}:{$operation}";
        if ($suffix) {
            $key .= ":{$suffix}";
        }
        return $key;
    }

    /**
     * Clear performance metrics cache.
     */
    public function clearCache(?string $syncType = null, ?string $operation = null): void
    {
        // In a real implementation, you would use pattern-based cache clearing
        // For now, we'll just flush all cache (not recommended for production)
        Cache::flush();
        
        Log::info('Performance metrics cache cleared', [
            'sync_type' => $syncType,
            'operation' => $operation,
        ]);
    }

    /**
     * Get real-time performance alerts.
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        $now = now();
        $currentHour = $now->format('Y-m-d-H');

        // Check for high failure rates
        $failureThreshold = 10; // 10% failure rate threshold
        
        // This is a simplified implementation
        // In production, you'd check all sync types and operations
        $syncTypes = ['order', 'inventory', 'payment'];
        $operations = ['create', 'update', 'delete'];

        foreach ($syncTypes as $syncType) {
            foreach ($operations as $operation) {
                $key = $this->getCacheKey('hourly', $syncType, $operation, $currentHour);
                $data = Cache::get($key, []);

                if (isset($data['total']) && $data['total'] > 10) { // Only alert if significant volume
                    $failureRate = ($data['failure'] ?? 0) / $data['total'] * 100;
                    
                    if ($failureRate > $failureThreshold) {
                        $alerts[] = [
                            'type' => 'high_failure_rate',
                            'sync_type' => $syncType,
                            'operation' => $operation,
                            'failure_rate' => round($failureRate, 2),
                            'threshold' => $failureThreshold,
                            'total_operations' => $data['total'],
                            'failed_operations' => $data['failure'] ?? 0,
                            'severity' => $failureRate > 25 ? 'critical' : 'warning',
                        ];
                    }
                }

                // Check for slow operations
                if (isset($data['total_time']) && $data['total'] > 0) {
                    $avgTime = $data['total_time'] / $data['total'];
                    if ($avgTime > 10) { // 10 seconds threshold
                        $alerts[] = [
                            'type' => 'slow_operations',
                            'sync_type' => $syncType,
                            'operation' => $operation,
                            'avg_processing_time' => round($avgTime, 3),
                            'threshold' => 10,
                            'total_operations' => $data['total'],
                            'severity' => $avgTime > 30 ? 'critical' : 'warning',
                        ];
                    }
                }
            }
        }

        return $alerts;
    }
}