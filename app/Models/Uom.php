<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];
}

