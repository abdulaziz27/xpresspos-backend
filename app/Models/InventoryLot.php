<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLot extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'inventory_item_id',
        'lot_code',
        'mfg_date',
        'exp_date',
        'initial_qty',
        'remaining_qty',
        'unit_cost',
        'status',
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
     * Get the tenant that owns the inventory lot.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'mfg_date' => 'date',
        'exp_date' => 'date',
        'initial_qty' => 'decimal:3',
        'remaining_qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

