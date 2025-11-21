<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\BelongsToStore;

class Table extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'table_number',
        'name',
        'capacity',
        'status',
        'location',
        'is_active',
        'occupied_at',
        'last_cleared_at',
        'current_order_id',
        'total_occupancy_count',
        'average_occupancy_duration',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'occupied_at' => 'datetime',
        'last_cleared_at' => 'datetime',
        'total_occupancy_count' => 'integer',
        'average_occupancy_duration' => 'decimal:2',
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
     * Get the tenant that owns the table.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the orders for the table.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the current active order for the table.
     */
    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }

    /**
     * Get the occupancy histories for the table.
     */
    public function occupancyHistories(): HasMany
    {
        return $this->hasMany(TableOccupancyHistory::class);
    }

    /**
     * Get the current active occupancy.
     */
    public function currentOccupancy(): ?TableOccupancyHistory
    {
        return $this->occupancyHistories()->occupied()->latest()->first();
    }

    /**
     * Get the current occupancy relationship for eager loading.
     */
    public function currentOccupancyRelation(): HasOne
    {
        return $this->hasOne(TableOccupancyHistory::class)->occupied()->latest();
    }

    /**
     * Check if table is occupied.
     */
    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    /**
     * Check if table is available.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available' && $this->is_active;
    }

    /**
     * Mark table as occupied with detailed tracking.
     */
    public function occupy(?Order $order = null, ?int $partySize = null, $userId = null): TableOccupancyHistory
    {
        $occupiedAt = now();
        $user = $userId ?? (auth()->id() ?? (request()->user()?->id));

        $this->update([
            'status' => 'occupied',
            'occupied_at' => $occupiedAt,
            'current_order_id' => $order?->id,
            'total_occupancy_count' => $this->total_occupancy_count + 1,
        ]);

        return $this->occupancyHistories()->create([
            'tenant_id' => $this->tenant_id,
            'store_id' => $this->store_id,
            'order_id' => $order?->id,
            'user_id' => $user,
            'occupied_at' => $occupiedAt,
            'party_size' => $partySize,
            'status' => 'occupied',
        ]);
    }

    /**
     * Mark table as available and clear occupancy.
     */
    public function makeAvailable(): void
    {
        $clearedAt = now();
        $currentOccupancy = $this->currentOccupancy();

        $this->update([
            'status' => 'available',
            'last_cleared_at' => $clearedAt,
            'current_order_id' => null,
        ]);

        if ($currentOccupancy) {
            $currentOccupancy->update([
                'cleared_at' => $clearedAt,
                'status' => 'cleared',
                'order_total' => $this->currentOrder?->total_amount,
            ]);

            $currentOccupancy->calculateDuration();
            $this->updateAverageOccupancyDuration();
        }
    }

    /**
     * Update average occupancy duration.
     */
    private function updateAverageOccupancyDuration(): void
    {
        $averageDuration = $this->occupancyHistories()
            ->cleared()
            ->whereNotNull('duration_minutes')
            ->avg('duration_minutes');

        if ($averageDuration) {
            $this->update(['average_occupancy_duration' => round($averageDuration, 2)]);
        }
    }

    /**
     * Scope to get available tables.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    /**
     * Scope to get occupied tables.
     */
    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    /**
     * Scope to get active tables.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get table occupancy statistics.
     */
    public function getOccupancyStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $histories = $this->occupancyHistories()
            ->dateRange($startDate, now())
            ->get();

        $totalOccupancies = $histories->count();
        $clearedOccupancies = $histories->where('status', 'cleared');

        return [
            'total_occupancies' => $totalOccupancies,
            'cleared_occupancies' => $clearedOccupancies->count(),
            'average_duration' => $clearedOccupancies->avg('duration_minutes') ?? 0,
            'total_revenue' => $clearedOccupancies->sum('order_total') ?? 0,
            'average_party_size' => $histories->whereNotNull('party_size')->avg('party_size') ?? 0,
            'utilization_rate' => $this->calculateUtilizationRate($days),
        ];
    }

    /**
     * Calculate table utilization rate.
     */
    private function calculateUtilizationRate(int $days): float
    {
        $totalMinutesInPeriod = $days * 24 * 60; // Total minutes in the period
        $occupiedMinutes = $this->occupancyHistories()
            ->cleared()
            ->where('occupied_at', '>=', now()->subDays($days))
            ->sum('duration_minutes');

        return $totalMinutesInPeriod > 0 ? round(($occupiedMinutes / $totalMinutesInPeriod) * 100, 2) : 0;
    }

    /**
     * Get current occupancy duration in minutes.
     */
    public function getCurrentOccupancyDuration(): int
    {
        if (!$this->isOccupied() || !$this->occupied_at) {
            return 0;
        }

        return $this->occupied_at->diffInMinutes(now());
    }

    /**
     * Check if table has been occupied too long (over 3 hours).
     */
    public function isOccupiedTooLong(): bool
    {
        return $this->getCurrentOccupancyDuration() > 180; // 3 hours
    }
}
