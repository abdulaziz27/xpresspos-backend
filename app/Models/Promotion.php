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
}


