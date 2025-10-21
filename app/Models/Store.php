<?php

namespace App\Models;

use App\Models\Discount;
use App\Models\StoreUserAssignment;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Store extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'settings',
        'status',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the users for the store.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(StoreUserAssignment::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user_assignments')
            ->withPivot(['assignment_role', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Get the subscription for the store (alias for activeSubscription).
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    /**
     * Get the active subscription for the store.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    /**
     * Get all subscriptions for the store.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the categories for the store.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the products for the store.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the orders for the store.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the member tiers for the store.
     */
    public function memberTiers(): HasMany
    {
        return $this->hasMany(MemberTier::class);
    }

    /**
     * Get the members for the store.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get the discounts for the store.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    /**
     * Check if store has exceeded hard limit for a feature.
     */
    public function hasExceededHardLimit(string $feature, int $limit): bool
    {
        $currentUsage = $this->getCurrentUsage($feature);
        return $currentUsage >= $limit;
    }

    /**
     * Check if store has exceeded transaction quota.
     */
    public function hasExceededTransactionQuota(): bool
    {
        $subscription = $this->activeSubscription;
        if (!$subscription) {
            return false;
        }

        $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
        if (!$usage || !$usage->annual_quota) {
            return false;
        }

        return $usage->current_usage >= $usage->annual_quota;
    }

    /**
     * Get current usage for a feature.
     */
    public function getCurrentUsage(string $feature): int
    {
        return match ($feature) {
            'products' => $this->getCurrentProductUsage(),
            'users' => $this->getCurrentUserUsage(),
            'categories' => $this->getCurrentCategoryUsage(),
            'transactions' => $this->getTransactionUsage(),
            default => 0,
        };
    }

    /**
     * Get current product usage, bypassing scope if no authenticated user.
     */
    private function getCurrentProductUsage(): int
    {
        $user = auth()->user();

        if (!$user) {
            // In testing or no auth context, use direct query
            return \App\Models\Product::withoutStoreScope()->where('store_id', $this->id)->count();
        }

        return $this->products()->count();
    }

    /**
     * Get current user usage, bypassing scope if no authenticated user.
     */
    private function getCurrentUserUsage(): int
    {
        $user = auth()->user();

        if (!$user) {
            // In testing or no auth context, use direct query (User doesn't have store scope)
            return \App\Models\User::where('store_id', $this->id)->count();
        }

        return $this->userAssignments()->count();
    }

    /**
     * Get current category usage, bypassing scope if no authenticated user.
     */
    private function getCurrentCategoryUsage(): int
    {
        $user = auth()->user();

        if (!$user) {
            // In testing or no auth context, use direct query
            return \App\Models\Category::withoutStoreScope()->where('store_id', $this->id)->count();
        }

        return $this->categories()->count();
    }

    /**
     * Get transaction usage for current subscription year.
     */
    private function getTransactionUsage(): int
    {
        $subscription = $this->activeSubscription;
        if (!$subscription) {
            return 0;
        }

        $usage = $subscription->usage()->where('feature_type', 'transactions')->first();
        return $usage ? $usage->current_usage : 0;
    }
}
