<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierItem extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $item): void {
            if (! $item->tenant_id && auth()->check()) {
                $tenantId = auth()->user()?->currentTenant()?->id;

                if (! $tenantId) {
                    throw new \RuntimeException('Tidak dapat menentukan tenant aktif untuk modifier item.');
                }

                $item->tenant_id = $tenantId;
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'modifier_group_id',
        'name',
        'description',
        'price_delta',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}

