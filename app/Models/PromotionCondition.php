<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'promotion_id',
        'condition_type',
        'condition_value',
    ];

    protected $casts = [
        'condition_value' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $condition): void {
            if ($condition->promotion && ! $condition->tenant_id) {
                $condition->tenant_id = $condition->promotion->tenant_id;
            } elseif (! $condition->tenant_id && auth()->check()) {
                $condition->tenant_id = auth()->user()?->currentTenant()?->id;
            }
        });
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Get minimum spend amount for MIN_SPEND condition.
     * Returns 0 if condition is not MIN_SPEND.
     */
    public function getMinSpendAmount(): float
    {
        if ($this->condition_type !== 'MIN_SPEND') {
            return 0;
        }

        return (float) ($this->condition_value['amount'] ?? 0);
    }

    /**
     * Get product IDs for ITEM_INCLUDE condition.
     * Returns empty array if condition is not ITEM_INCLUDE.
     */
    public function getProductIds(): array
    {
        if ($this->condition_type !== 'ITEM_INCLUDE') {
            return [];
        }

        return $this->condition_value['product_ids'] ?? [];
    }

    /**
     * Get tier IDs for CUSTOMER_TIER_IN condition.
     * Returns empty array if condition is not CUSTOMER_TIER_IN.
     */
    public function getTierIds(): array
    {
        if ($this->condition_type !== 'CUSTOMER_TIER_IN') {
            return [];
        }

        return $this->condition_value['tier_ids'] ?? [];
    }

    /**
     * Get days of week for DOW condition.
     * Returns empty array if condition is not DOW.
     * Day values: 1 = Monday, 7 = Sunday
     */
    public function getDaysOfWeek(): array
    {
        if ($this->condition_type !== 'DOW') {
            return [];
        }

        return $this->condition_value['days'] ?? [];
    }

    /**
     * Get time range for TIME_RANGE condition.
     * Returns null if condition is not TIME_RANGE.
     * 
     * @return array{start_time: string, end_time: string}|null
     */
    public function getTimeRange(): ?array
    {
        if ($this->condition_type !== 'TIME_RANGE') {
            return null;
        }

        return [
            'start_time' => $this->condition_value['start_time'] ?? '00:00',
            'end_time' => $this->condition_value['end_time'] ?? '23:59',
        ];
    }

    /**
     * Get store IDs for BRANCH_IN condition.
     * Returns empty array if condition is not BRANCH_IN.
     */
    public function getStoreIds(): array
    {
        if ($this->condition_type !== 'BRANCH_IN') {
            return [];
        }

        return $this->condition_value['store_ids'] ?? [];
    }

    /**
     * Check if condition is NEW_CUSTOMER type.
     */
    public function isNewCustomer(): bool
    {
        return $this->condition_type === 'NEW_CUSTOMER';
    }
}


