<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPointTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'member_id',
        'order_id',
        'user_id',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'reason',
        'description',
        'metadata',
        'expires_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                // Try to get from member first
                if ($model->member_id) {
                    $member = Member::find($model->member_id);
                    if ($member && $member->tenant_id) {
                        $model->tenant_id = $member->tenant_id;
                    }
                }
                
                // Fallback to store
                if (!$model->tenant_id && $model->store_id) {
                    $store = Store::find($model->store_id);
                    if ($store) {
                        $model->tenant_id = $store->tenant_id;
                    }
                }
            }
        });
    }

    /**
     * Get the tenant that owns the loyalty point transaction.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the member that owns the transaction.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the order associated with the transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who processed the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get earned points.
     */
    public function scopeEarned($query)
    {
        return $query->where('type', 'earned');
    }

    /**
     * Scope to get redeemed points.
     */
    public function scopeRedeemed($query)
    {
        return $query->where('type', 'redeemed');
    }

    /**
     * Scope to get adjusted points.
     */
    public function scopeAdjusted($query)
    {
        return $query->where('type', 'adjusted');
    }

    /**
     * Scope to get expired points.
     */
    public function scopeExpired($query)
    {
        return $query->where('type', 'expired');
    }

    /**
     * Scope to get transactions within date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if the transaction is positive (adds points).
     */
    public function isPositive(): bool
    {
        return $this->points > 0;
    }

    /**
     * Check if the transaction is negative (removes points).
     */
    public function isNegative(): bool
    {
        return $this->points < 0;
    }
}
