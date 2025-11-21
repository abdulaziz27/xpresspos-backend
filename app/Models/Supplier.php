<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        'bank_account',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $supplier): void {
            if (! $supplier->tenant_id && auth()->check()) {
                $supplier->tenant_id = auth()->user()->currentTenant()?->id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $query): void {
            if ($tenantId = auth()->user()?->currentTenant()?->id) {
                $query->where('tenant_id', $tenantId);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

