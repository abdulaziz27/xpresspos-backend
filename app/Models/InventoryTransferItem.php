<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferItem extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'inventory_transfer_id',
        'inventory_item_id',
        'uom_id',
        'quantity_shipped',
        'quantity_received',
        'unit_cost',
    ];

    protected $casts = [
        'quantity_shipped' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            // Enforce uom_id = inventoryItem->uom_id (base UOM)
            if ($item->inventory_item_id) {
                $inventoryItem = InventoryItem::find($item->inventory_item_id);
                if ($inventoryItem && $inventoryItem->uom_id) {
                    $item->uom_id = $inventoryItem->uom_id;
                }
            }
        });
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}


