<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'promotion_id',
        'condition_type',
        'condition_value',
    ];

    protected $casts = [
        'condition_value' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $condition): void {
            if ($condition->promotion && ! $condition->tenant_id) {
                $condition->tenant_id = $condition->promotion->tenant_id;
            } elseif (! $condition->tenant_id && auth()->check()) {
                $condition->tenant_id = auth()->user()?->currentTenant()?->id;
            }
        });
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}


