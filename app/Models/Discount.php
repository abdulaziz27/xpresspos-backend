<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'name',
        'description',
        'type',
        'value',
        'status',
        'expired_date'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expired_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            // Auto-fill tenant_id from currentTenant
            if (!$model->tenant_id && auth()->check()) {
                $user = auth()->user();
                $tenantId = $user->currentTenant()?->id;

                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                } elseif ($model->store_id) {
                    // Fallback: Get tenant_id from store if available
                    $store = Store::find($model->store_id);
                    if ($store) {
                        $model->tenant_id = $store->tenant_id;
                    }
                }
            }
        });
    }

    /**
     * Get the tenant that owns the discount.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the store that owns the discount.
     * Returns null for global discounts (applies to all stores).
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope a query to only include active discounts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to only include discounts that are still valid.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('expired_date')
                ->orWhere('expired_date', '>=', now()->toDateString());
        });
    }

    /**
     * Scope to get discounts for a specific store.
     * Includes global discounts (store_id is null).
     */
    public function scopeForStore($query, ?string $storeId)
    {
        return $query->where(function ($q) use ($storeId) {
            $q->whereNull('store_id') // Global discounts
                ->orWhere('store_id', $storeId); // Store-specific discounts
        });
    }
}
