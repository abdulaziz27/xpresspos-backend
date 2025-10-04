<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class MemberTier extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'name',
        'slug',
        'min_points',
        'max_points',
        'discount_percentage',
        'benefits',
        'color',
        'sort_order',
        'is_active',
        'description',
    ];

    protected $casts = [
        'min_points' => 'integer',
        'max_points' => 'integer',
        'discount_percentage' => 'decimal:2',
        'benefits' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the members in this tier.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'tier_id');
    }

    /**
     * Scope to get active tiers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_points');
    }

    /**
     * Check if a member with given points qualifies for this tier.
     */
    public function qualifiesForTier(int $points): bool
    {
        if ($points < $this->min_points) {
            return false;
        }

        if ($this->max_points !== null && $points > $this->max_points) {
            return false;
        }

        return true;
    }

    /**
     * Get the next tier for progression.
     */
    public function getNextTier(): ?self
    {
        return static::where('store_id', $this->store_id)
            ->where('min_points', '>', $this->min_points)
            ->active()
            ->ordered()
            ->first();
    }

    /**
     * Get points needed to reach this tier.
     */
    public function getPointsNeeded(int $currentPoints): int
    {
        return max(0, $this->min_points - $currentPoints);
    }
}
