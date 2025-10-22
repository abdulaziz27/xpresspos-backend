<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class Member extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'member_number',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'address',
        'loyalty_points',
        'total_spent',
        'visit_count',
        'last_visit_at',
        'tier_id',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'loyalty_points' => 'integer',
        'total_spent' => 'decimal:2',
        'visit_count' => 'integer',
        'last_visit_at' => 'datetime',
        'is_active' => 'boolean',
    ];



    /**
     * Get the orders for the member.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the member's tier.
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(MemberTier::class);
    }

    /**
     * Get the member's loyalty point transactions.
     */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }

    /**
     * Add loyalty points with transaction tracking.
     */
    public function addLoyaltyPoints(int $points, string $reason = null, array $metadata = []): LoyaltyPointTransaction
    {
        $balanceBefore = $this->loyalty_points;
        $this->increment('loyalty_points', $points);
        $balanceAfter = $this->fresh()->loyalty_points;

        // Update tier if necessary
        $this->updateTier();

        return $this->loyaltyTransactions()->create([
            'store_id' => $this->store_id,
            'order_id' => $metadata['order_id'] ?? null,
            'user_id' => auth()->id(),
            'type' => 'earned',
            'points' => $points,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reason' => $reason ?? 'Points earned',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Redeem loyalty points with transaction tracking.
     */
    public function redeemLoyaltyPoints(int $points, string $reason = null, array $metadata = []): bool
    {
        if ($this->loyalty_points < $points) {
            return false;
        }

        $balanceBefore = $this->loyalty_points;
        $this->decrement('loyalty_points', $points);
        $balanceAfter = $this->fresh()->loyalty_points;

        // Update tier if necessary
        $this->updateTier();

        $this->loyaltyTransactions()->create([
            'store_id' => $this->store_id,
            'order_id' => $metadata['order_id'] ?? null,
            'user_id' => auth()->id(),
            'type' => 'redeemed',
            'points' => -$points, // Negative for redemption
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reason' => $reason ?? 'Points redeemed',
            'metadata' => $metadata,
        ]);

        return true;
    }

    /**
     * Adjust loyalty points (manual adjustment by staff).
     */
    public function adjustLoyaltyPoints(int $points, string $reason, array $metadata = []): LoyaltyPointTransaction
    {
        $balanceBefore = $this->loyalty_points;
        $this->increment('loyalty_points', $points);
        $balanceAfter = $this->fresh()->loyalty_points;

        // Update tier if necessary
        $this->updateTier();

        return $this->loyaltyTransactions()->create([
            'store_id' => $this->store_id,
            'user_id' => auth()->id(),
            'type' => 'adjusted',
            'points' => $points,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Update spending and visit statistics.
     */
    public function updateStats(float $amount): void
    {
        $this->increment('total_spent', $amount);
        $this->increment('visit_count', 1);
        $this->update(['last_visit_at' => now()]);
    }

    /**
     * Scope to get active members.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get members with high loyalty points.
     */
    public function scopeHighLoyalty($query, int $threshold = 1000)
    {
        return $query->where('loyalty_points', '>=', $threshold);
    }

    /**
     * Scope to get members by tier.
     */
    public function scopeByTier($query, $tierId)
    {
        return $query->where('tier_id', $tierId);
    }

    /**
     * Get the member's current tier based on points.
     */
    public function getCurrentTier(): ?MemberTier
    {
        return MemberTier::where('store_id', $this->store_id)
            ->where('min_points', '<=', $this->loyalty_points)
            ->where(function ($query) {
                $query->whereNull('max_points')
                    ->orWhere('max_points', '>=', $this->loyalty_points);
            })
            ->active()
            ->ordered()
            ->first();
    }

    /**
     * Update member's tier based on current points.
     */
    public function updateTier(): void
    {
        $newTier = $this->getCurrentTier();
        if ($newTier && $this->tier_id !== $newTier->id) {
            $this->update(['tier_id' => $newTier->id]);
        }
    }

    /**
     * Get points needed for next tier.
     */
    public function getPointsToNextTier(): int
    {
        $currentTier = $this->getCurrentTier();
        if (!$currentTier) {
            $firstTier = MemberTier::where('store_id', $this->store_id)
                ->active()
                ->ordered()
                ->first();
            return $firstTier ? $firstTier->min_points - $this->loyalty_points : 0;
        }

        $nextTier = $currentTier->getNextTier();
        return $nextTier ? $nextTier->min_points - $this->loyalty_points : 0;
    }

    /**
     * Get member's tier discount percentage.
     */
    public function getTierDiscountPercentage(): float
    {
        return $this->tier?->discount_percentage ?? 0;
    }

    /**
     * Calculate loyalty points earned from purchase amount.
     */
    public function calculatePointsFromPurchase(float $amount): int
    {
        // Default: 1 point per $1 spent, can be customized per store
        $pointsPerDollar = 1;
        
        // Apply tier multiplier if available
        $tierMultiplier = $this->tier?->benefits['points_multiplier'] ?? 1;
        
        return intval(floor($amount * $pointsPerDollar * $tierMultiplier));
    }

    /**
     * Get member activity summary.
     */
    public function getActivitySummary(int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'orders_count' => $this->orders()->where('created_at', '>=', $startDate)->count(),
            'total_spent' => $this->orders()->where('created_at', '>=', $startDate)->sum('total_amount'),
            'points_earned' => $this->loyaltyTransactions()
                ->earned()
                ->where('created_at', '>=', $startDate)
                ->sum('points'),
            'points_redeemed' => abs($this->loyaltyTransactions()
                ->redeemed()
                ->where('created_at', '>=', $startDate)
                ->sum('points')),
            'last_visit' => $this->last_visit_at,
            'days_since_last_visit' => $this->last_visit_at ? $this->last_visit_at->diffInDays(now()) : null,
        ];
    }
}
