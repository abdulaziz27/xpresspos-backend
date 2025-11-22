<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class TableOccupancyHistory extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'table_id',
        'order_id',
        'user_id',
        'occupied_at',
        'cleared_at',
        'duration_minutes',
        'party_size',
        'order_total',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'occupied_at' => 'datetime',
        'cleared_at' => 'datetime',
        'duration_minutes' => 'integer',
        'party_size' => 'integer',
        'order_total' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->tenant_id && $model->store_id) {
                $store = Store::find($model->store_id);
                if ($store) {
                    $model->tenant_id = $store->tenant_id;
                }
            }
        });
    }

    /**
     * Get the tenant that owns the table occupancy history.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the table that owns the occupancy history.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Get the order associated with the occupancy.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who processed the occupancy.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get occupied records.
     */
    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    /**
     * Scope to get cleared records.
     */
    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    /**
     * Scope to get records within date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occupied_at', [$startDate, $endDate]);
    }

    /**
     * Calculate and set duration when clearing.
     */
    public function calculateDuration(): void
    {
        if ($this->occupied_at && $this->cleared_at) {
            $this->duration_minutes = $this->occupied_at->diffInMinutes($this->cleared_at);
            $this->save();
        }
    }

    /**
     * Check if the occupancy is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'occupied' && is_null($this->cleared_at);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration_minutes) {
            return 'N/A';
        }

        $hours = intval($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}
