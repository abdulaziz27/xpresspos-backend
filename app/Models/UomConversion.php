<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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


