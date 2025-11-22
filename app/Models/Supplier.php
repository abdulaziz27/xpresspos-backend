<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
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
        'store_id', // Optional, nullable - supplier is tenant-scoped, not store-scoped
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
        // Use TenantScope for proper tenant scoping
        static::addGlobalScope(new TenantScope);

        // Automatically set tenant_id when creating
        static::creating(function (self $supplier): void {
            if (! $supplier->tenant_id && auth()->check()) {
                $user = auth()->user();
                $tenantId = $user->currentTenant()?->id;
                
                if (!$tenantId) {
                    throw new \Exception('Tidak dapat menentukan tenant aktif untuk pengguna.');
                }
                
                $supplier->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the store (optional - supplier is tenant-scoped, not store-scoped).
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}

