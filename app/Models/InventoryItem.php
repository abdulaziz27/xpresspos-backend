<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToStore;

    protected $fillable = [
        'store_id',
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

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }
}

