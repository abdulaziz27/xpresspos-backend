<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class SyncHistory extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'idempotency_key',
        'sync_type',
        'operation',
        'entity_type',
        'entity_id',
        'payload',
        'conflicts',
        'status',
        'error_message',
        'retry_count',
        'last_retry_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'conflicts' => 'array',
        'retry_count' => 'integer',
        'last_retry_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Sync types
    const TYPE_ORDER = 'order';
    const TYPE_INVENTORY = 'inventory';
    const TYPE_PAYMENT = 'payment';
    const TYPE_PRODUCT = 'product';
    const TYPE_MEMBER = 'member';
    const TYPE_EXPENSE = 'expense';

    // Operations
    const OPERATION_CREATE = 'create';
    const OPERATION_UPDATE = 'update';
    const OPERATION_DELETE = 'delete';

    // Status values
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CONFLICT = 'conflict';

    /**
     * Get the user who initiated the sync.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the synced entity (polymorphic).
     */
    public function entity()
    {
        return $this->morphTo('entity', 'entity_type', 'entity_id');
    }

    /**
     * Scope to get sync records by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('sync_type', $type);
    }

    /**
     * Scope to get sync records by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending sync records.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get failed sync records.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to get conflicted sync records.
     */
    public function scopeConflicted($query)
    {
        return $query->where('status', self::STATUS_CONFLICT);
    }

    /**
     * Check if sync has conflicts.
     */
    public function hasConflicts(): bool
    {
        return $this->status === self::STATUS_CONFLICT && !empty($this->conflicts);
    }

    /**
     * Mark sync as completed.
     */
    public function markCompleted(?string $entityId = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'entity_id' => $entityId ?? $this->entity_id,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark sync as failed.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    /**
     * Mark sync as conflicted.
     */
    public function markConflicted(array $conflicts): void
    {
        $this->update([
            'status' => self::STATUS_CONFLICT,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Check if sync can be retried.
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->retry_count < $maxRetries && 
               in_array($this->status, [self::STATUS_FAILED, self::STATUS_PENDING]);
    }

    /**
     * Create a sync history record.
     */
    public static function createSync(
        string $idempotencyKey,
        string $syncType,
        string $operation,
        string $entityType,
        array $payload,
        ?string $entityId = null
    ): self {
        return self::create([
            'store_id' => auth()->user()->store_id,
            'user_id' => auth()->id(),
            'idempotency_key' => $idempotencyKey,
            'sync_type' => $syncType,
            'operation' => $operation,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Find sync by idempotency key.
     */
    public static function findByIdempotencyKey(string $key): ?self
    {
        return self::where('idempotency_key', $key)->first();
    }

    /**
     * Check if idempotency key exists.
     */
    public static function idempotencyKeyExists(string $key): bool
    {
        return self::where('idempotency_key', $key)->exists();
    }
}