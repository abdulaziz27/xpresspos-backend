<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, BelongsToStore;

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
     * Get the tenant that owns the discount.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
}
