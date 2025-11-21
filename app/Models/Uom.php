<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'uoms';

    protected $fillable = [
        'id',
        'code',
        'name',
        'type',
        'precision',
        'is_active',
        'description',
    ];

    public function conversions(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'from_uom_id');
    }

    public function inverseConversions(): HasMany
    {
        return $this->hasMany(UomConversion::class, 'to_uom_id');
    }
}

