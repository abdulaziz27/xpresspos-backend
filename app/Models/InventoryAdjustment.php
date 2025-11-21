<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Store;

class InventoryAdjustment extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToStore;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_COUNT_DIFF = 'COUNT_DIFF';
    public const REASON_EXPIRED = 'EXPIRED';
    public const REASON_DAMAGE = 'DAMAGE';
    public const REASON_INITIAL = 'INITIAL';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',
        'adjustment_number',
        'status',
        'reason',
        'adjusted_at',
        'notes',
    ];

    protected $casts = [
        'adjusted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if (! $adjustment->tenant_id && $adjustment->store_id) {
                $store = Store::find($adjustment->store_id);

                if ($store) {
                    $adjustment->tenant_id = $store->tenant_id;
                }
            }

            if (! $adjustment->user_id && auth()->check()) {
                $adjustment->user_id = auth()->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }
}


