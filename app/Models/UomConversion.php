<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UOM Conversion Model
 * 
 * @deprecated UOM conversions are not used in runtime for Wave UOM Simplification.
 * All quantities are stored in base UOM from inventory_items.uom_id.
 * This model and table are kept for future use, but conversion logic is disabled.
 */
class UomConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_uom_id',
        'to_uom_id',
        'multiplier',
    ];

    protected $casts = [
        'multiplier' => 'decimal:6',
    ];

    public function from(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'from_uom_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'to_uom_id');
    }
}


