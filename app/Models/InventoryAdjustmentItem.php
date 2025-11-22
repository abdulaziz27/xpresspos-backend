<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentItem extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'inventory_adjustment_id',
        'inventory_item_id',
        'system_qty',
        'counted_qty',
        'difference_qty',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'system_qty' => 'decimal:3',
        'counted_qty' => 'decimal:3',
        'difference_qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            // Auto-calculate difference_qty
            if ($item->counted_qty !== null && $item->system_qty !== null) {
                $item->difference_qty = $item->counted_qty - $item->system_qty;
            }

            // Auto-calculate total_cost
            if ($item->unit_cost !== null && $item->difference_qty !== null) {
                $item->total_cost = abs($item->difference_qty) * $item->unit_cost;
            }
        });
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}


