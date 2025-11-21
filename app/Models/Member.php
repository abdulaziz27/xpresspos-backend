<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Store;

class Member extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
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
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($member) {
            // Auto-set tenant_id from context or store
            if (!$member->tenant_id) {
                if ($member->store_id) {
                    $store = Store::find($member->store_id);
                    if ($store) {
                        $member->tenant_id = $store->tenant_id;
                    }
                } else {
                    $user = auth()->user();
                    if ($user) {
                        $tenant = $user->currentTenant();
                        if ($tenant) {
                            $member->tenant_id = $tenant->id;
                        }
                    }
                }
            }
            
            if (empty($member->member_number)) {
                $member->member_number = static::generateMemberNumber($member->tenant_id);
            }
        });
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Get the tenant that owns the member.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Generate unique member number for the tenant.
     */
    protected static function generateMemberNumber(string $tenantId): string
    {
        $prefix = 'MBR';
        $date = now()->format('Ymd');
        
        // Get last member number for today in this tenant
        $lastMember = static::where('tenant_id', $tenantId)
            ->where('member_number', 'like', $prefix . $date . '%')
            ->orderBy('member_number', 'desc')
            ->first();
        
        if ($lastMember) {
            // Extract sequence number and increment
            $lastSequence = intval(substr($lastMember->member_number, -4));
            $sequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }
        
        return $prefix . $date . $sequence;
    }

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
    public function addLoyaltyPoints(int $points, ?string $reason = null, array $metadata = []): LoyaltyPointTransaction
    {
        $balanceBefore = $this->loyalty_points;
        $this->increment('loyalty_points', $points);
        $balanceAfter = $this->fresh()->loyalty_points;

        // Update tier only if crossed a boundary (optimized)
        if ($this->shouldCheckTierUpdate($balanceBefore, $balanceAfter)) {
            $this->updateTier();
        }

        // Get store_id from metadata or use member's store_id or get from order
        $storeId = $metadata['store_id'] ?? $this->store_id;
        if (!$storeId && isset($metadata['order_id'])) {
            $order = Order::find($metadata['order_id']);
            $storeId = $order?->store_id;
        }
        
        return $this->loyaltyTransactions()->create([
            'tenant_id' => $this->tenant_id,
            'store_id' => $storeId,
            'order_id' => $metadata['order_id'] ?? null,
            'user_id' => $metadata['user_id'] ?? auth()->id(), // Use order's user_id as fallback when auth() is null (Observer context)
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
    public function redeemLoyaltyPoints(int $points, ?string $reason = null, array $metadata = []): bool
    {
        if ($this->loyalty_points < $points) {
            return false;
        }

        $balanceBefore = $this->loyalty_points;
        $this->decrement('loyalty_points', $points);
        $balanceAfter = $this->fresh()->loyalty_points;

        // Update tier only if crossed a boundary (optimized)
        if ($this->shouldCheckTierUpdate($balanceBefore, $balanceAfter)) {
            $this->updateTier();
        }

        // Get store_id from metadata or use member's store_id or get from order
        $storeId = $metadata['store_id'] ?? $this->store_id;
        if (!$storeId && isset($metadata['order_id'])) {
            $order = Order::find($metadata['order_id']);
            $storeId = $order?->store_id;
        }
        
        $this->loyaltyTransactions()->create([
            'tenant_id' => $this->tenant_id,
            'store_id' => $storeId,
            'order_id' => $metadata['order_id'] ?? null,
            'user_id' => $metadata['user_id'] ?? auth()->id(), // Use order's user_id as fallback when auth() is null (Observer context)
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

        // Update tier only if crossed a boundary (optimized)
        if ($this->shouldCheckTierUpdate($balanceBefore, $balanceAfter)) {
            $this->updateTier();
        }

        // Get store_id from metadata or use member's store_id or get from order
        $storeId = $metadata['store_id'] ?? $this->store_id;
        if (!$storeId && isset($metadata['order_id'])) {
            $order = Order::find($metadata['order_id']);
            $storeId = $order?->store_id;
        }
        
        return $this->loyaltyTransactions()->create([
            'tenant_id' => $this->tenant_id,
            'store_id' => $storeId,
            'order_id' => $metadata['order_id'] ?? null,
            'user_id' => $metadata['user_id'] ?? auth()->id(), // Use order's user_id as fallback when auth() is null (Observer context)
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
        return MemberTier::where('tenant_id', $this->tenant_id)
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
     * Check if tier update is needed based on point boundaries.
     * This optimizes performance by only checking tier when crossing thresholds.
     */
    protected function shouldCheckTierUpdate(int $oldPoints, int $newPoints): bool
    {
        // Always check if points changed significantly or if no tier assigned
        if (!$this->tier_id) {
            return true;
        }

        $boundaries = $this->getTierBoundaries();
        
        // Check if crossed any tier boundary
        foreach ($boundaries as $threshold) {
            if (($oldPoints < $threshold && $newPoints >= $threshold) ||
                ($oldPoints >= $threshold && $newPoints < $threshold)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get tier boundaries (min_points) with caching.
     * Cached for 1 hour to avoid repeated database queries.
     */
    protected function getTierBoundaries(): array
    {
        $cacheKey = 'tier_boundaries_' . $this->tenant_id;
        
        return cache()->remember($cacheKey, 3600, function() {
            return MemberTier::where('tenant_id', $this->tenant_id)
                ->active()
                ->orderBy('min_points')
                ->pluck('min_points')
                ->toArray();
        });
    }

    /**
     * Get points needed for next tier.
     */
    public function getPointsToNextTier(): int
    {
        $currentTier = $this->getCurrentTier();
        if (!$currentTier) {
            $firstTier = MemberTier::where('tenant_id', $this->tenant_id)
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
     * 
     * For Rupiah currency: 1 point per Rp 1.000 spent
     * Can be customized per store in the future
     */
    public function calculatePointsFromPurchase(float $amount): int
    {
        // For Rupiah: 1 point per Rp 1.000 spent
        $pointsPerThousand = 1; // 1 point per Rp 1.000
        
        // Apply tier multiplier if available
        $tierMultiplier = $this->tier?->benefits['points_multiplier'] ?? 1;
        
        // Calculate: (amount / 1000) * pointsPerThousand * tierMultiplier
        // Example: Rp 35.000 / 1000 * 1 * 1 = 35 points
        return intval(floor(($amount / 1000) * $pointsPerThousand * $tierMultiplier));
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
