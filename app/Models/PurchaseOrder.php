<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'supplier_id',
        'po_number',
        'status',
        'ordered_at',
        'received_at',
        'total_amount',
        'notes',
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
     * Get the tenant that owns the purchase order.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}

