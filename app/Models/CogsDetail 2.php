<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CogsDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cogs_details';

    protected $fillable = [
        'cogs_history_id',
        'order_item_id',
        'inventory_item_id',
        'lot_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    /**
     * Get the COGS history that owns this detail.
     */
    public function cogsHistory(): BelongsTo
    {
        return $this->belongsTo(CogsHistory::class);
    }

    /**
     * Get the order item associated with this detail.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Get the inventory item associated with this detail.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the lot associated with this detail.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(InventoryLot::class, 'lot_id');
    }
}

