<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'promotion_id',
        'reward_type',
        'reward_value',
    ];

    protected $casts = [
        'reward_value' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $reward): void {
            if ($reward->promotion && ! $reward->tenant_id) {
                $reward->tenant_id = $reward->promotion->tenant_id;
            } elseif (! $reward->tenant_id && auth()->check()) {
                $reward->tenant_id = auth()->user()?->currentTenant()?->id;
            }
        });
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Get discount percentage for PCT_OFF reward.
     * Returns 0 if reward is not PCT_OFF.
     */
    public function getPercentage(): float
    {
        if ($this->reward_type !== 'PCT_OFF') {
            return 0;
        }

        return (float) ($this->reward_value['percentage'] ?? 0);
    }

    /**
     * Get discount amount for AMOUNT_OFF reward.
     * Returns 0 if reward is not AMOUNT_OFF.
     */
    public function getAmount(): float
    {
        if ($this->reward_type !== 'AMOUNT_OFF') {
            return 0;
        }

        return (float) ($this->reward_value['amount'] ?? 0);
    }

    /**
     * Get buy quantity for BUY_X_GET_Y reward.
     * Returns 1 if reward is not BUY_X_GET_Y.
     */
    public function getBuyQuantity(): int
    {
        if ($this->reward_type !== 'BUY_X_GET_Y') {
            return 1;
        }

        return (int) ($this->reward_value['buy_quantity'] ?? 1);
    }

    /**
     * Get free quantity for BUY_X_GET_Y reward.
     * Returns 0 if reward is not BUY_X_GET_Y.
     */
    public function getFreeQuantity(): int
    {
        if ($this->reward_type !== 'BUY_X_GET_Y') {
            return 0;
        }

        return (int) ($this->reward_value['get_quantity'] ?? 1);
    }

    /**
     * Get product ID for BUY_X_GET_Y reward.
     * Returns null if reward is not BUY_X_GET_Y or no product specified.
     */
    public function getProductId(): ?string
    {
        if ($this->reward_type !== 'BUY_X_GET_Y') {
            return null;
        }

        return $this->reward_value['product_id'] ?? null;
    }

    /**
     * Get points multiplier for POINTS_MULTIPLIER reward.
     * Returns 1.0 if reward is not POINTS_MULTIPLIER.
     */
    public function getMultiplier(): float
    {
        if ($this->reward_type !== 'POINTS_MULTIPLIER') {
            return 1.0;
        }

        return (float) ($this->reward_value['multiplier'] ?? 1.0);
    }

    /**
     * Calculate discount amount based on subtotal.
     * 
     * @param float $subtotal Order subtotal
     * @return float Discount amount
     */
    public function calculateDiscount(float $subtotal): float
    {
        return match ($this->reward_type) {
            'PCT_OFF' => $subtotal * ($this->getPercentage() / 100),
            'AMOUNT_OFF' => min($this->getAmount(), $subtotal), // Cap at subtotal
            'BUY_X_GET_Y', 'POINTS_MULTIPLIER' => 0, // These don't give direct discount
            default => 0,
        };
    }
}


