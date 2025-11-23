<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'name',
        'description',
        'type',
        'code',
        'stackable',
        'status',
        'starts_at',
        'ends_at',
        'priority',
    ];

    protected $casts = [
        'stackable' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $promotion): void {
            if (! $promotion->tenant_id && auth()->check()) {
                $promotion->tenant_id = auth()->user()?->currentTenant()?->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(PromotionReward::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Scope to get active promotions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get promotions that are currently valid (within date range).
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Scope to get promotions for a specific store.
     * Includes promotions that apply to all stores (store_id is null).
     */
    public function scopeForStore($query, ?string $storeId)
    {
        return $query->where(function ($q) use ($storeId) {
            $q->whereNull('store_id') // All stores
                ->orWhere('store_id', $storeId); // Specific store
        });
    }

    /**
     * Check if promotion is currently valid (active and within date range).
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if promotion applies to a specific store.
     */
    public function appliesToStore(?string $storeId): bool
    {
        // If store_id is null, promotion applies to all stores
        if ($this->store_id === null) {
            return true;
        }

        return $this->store_id === $storeId;
    }
}


