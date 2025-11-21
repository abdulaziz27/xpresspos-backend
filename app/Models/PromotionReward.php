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
}


