<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\TenantScope;

class InventoryItem extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        
        // Automatically set tenant_id when creating
        static::creating(function ($model) {
            if (!$model->tenant_id && auth()->check()) {
                $user = auth()->user();
                $tenantId = $user->currentTenant()?->id;
                
                if (!$tenantId) {
                    throw new \Exception('Tidak dapat menentukan tenant aktif untuk pengguna.');
                }
                
                $model->tenant_id = $tenantId;
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'category',
        'uom_id',
        'track_lot',
        'track_stock',
        'min_stock_level',
        'default_cost',
        'status',
    ];

    protected $casts = [
        'track_lot' => 'boolean',
        'track_stock' => 'boolean',
        'min_stock_level' => 'decimal:3',
        'default_cost' => 'decimal:4',
    ];

    /**
     * Get the tenant that owns the inventory item.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function adjustmentItems(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}

