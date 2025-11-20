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

