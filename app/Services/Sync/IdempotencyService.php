<?php

namespace App\Services\Sync;

use App\Models\SyncHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IdempotencyService
{
    protected int $cacheTimeout = 3600; // 1 hour

    /**
     * Check if an idempotency key is a duplicate.
     */
    public function isDuplicate(string $idempotencyKey): bool
    {
        // First check cache for performance
        $cacheKey = $this->getCacheKey($idempotencyKey);
        if (Cache::has($cacheKey)) {
            Log::debug('Idempotency key found in cache', ['key' => $idempotencyKey]);
            return true;
        }

        // Check database
        $exists = SyncHistory::idempotencyKeyExists($idempotencyKey);
        
        if ($exists) {
            // Cache the result to avoid future database queries
            Cache::put($cacheKey, true, $this->cacheTimeout);
            Log::debug('Idempotency key found in database and cached', ['key' => $idempotencyKey]);
        }

        return $exists;
    }

    /**
     * Mark an idempotency key as processed.
     */
    public function markProcessed(string $idempotencyKey, string $entityId): void
    {
        $cacheKey = $this->getCacheKey($idempotencyKey);
        Cache::put($cacheKey, $entityId, $this->cacheTimeout);
        
        Log::debug('Idempotency key marked as processed', [
            'key' => $idempotencyKey,
            'entity_id' => $entityId
        ]);
    }

    /**
     * Get the cached result for an idempotency key.
     */
    public function getCachedResult(string $idempotencyKey): mixed
    {
        $cacheKey = $this->getCacheKey($idempotencyKey);
        return Cache::get($cacheKey);
    }

    /**
     * Validate idempotency key format.
     */
    public function validateKey(string $idempotencyKey): bool
    {
        // Key should be non-empty and reasonable length
        if (empty($idempotencyKey) || strlen($idempotencyKey) > 255) {
            return false;
        }

        // Key should contain only alphanumeric characters, hyphens, and underscores
        return preg_match('/^[a-zA-Z0-9\-_]+$/', $idempotencyKey) === 1;
    }

    /**
     * Generate a cache key for idempotency.
     */
    protected function getCacheKey(string $idempotencyKey): string
    {
        $storeId = auth()->user()->store_id ?? 'unknown';
        return "sync:idempotency:{$storeId}:{$idempotencyKey}";
    }

    /**
     * Clear idempotency cache for a store.
     */
    public function clearStoreCache(?string $storeId = null): void
    {
        $storeId = $storeId ?? auth()->user()->store_id;
        $pattern = "sync:idempotency:{$storeId}:*";
        
        // Note: This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar
        Cache::flush(); // This clears all cache - in production, implement pattern-based clearing
        
        Log::info('Idempotency cache cleared for store', ['store_id' => $storeId]);
    }

    /**
     * Get idempotency statistics.
     */
    public function getStats(?string $storeId = null): array
    {
        $storeId = $storeId ?? auth()->user()->store_id;
        
        $stats = SyncHistory::where('store_id', $storeId)
            ->selectRaw('
                COUNT(*) as total_requests,
                COUNT(DISTINCT idempotency_key) as unique_keys,
                COUNT(*) - COUNT(DISTINCT idempotency_key) as duplicate_requests,
                AVG(retry_count) as avg_retries
            ')
            ->first();

        return [
            'total_requests' => $stats->total_requests ?? 0,
            'unique_keys' => $stats->unique_keys ?? 0,
            'duplicate_requests' => $stats->duplicate_requests ?? 0,
            'duplicate_rate' => $stats->total_requests > 0 ? 
                round(($stats->duplicate_requests / $stats->total_requests) * 100, 2) : 0,
            'avg_retries' => round($stats->avg_retries ?? 0, 2),
        ];
    }

    /**
     * Clean up old idempotency records.
     */
    public function cleanup(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        $deletedCount = SyncHistory::where('created_at', '<', $cutoffDate)
            ->where('status', SyncHistory::STATUS_COMPLETED)
            ->delete();

        Log::info('Idempotency cleanup completed', [
            'cutoff_date' => $cutoffDate,
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }
}