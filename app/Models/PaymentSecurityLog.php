<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event',
        'level',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'user_id',
        'user_email',
        'context',
        'headers',
    ];

    protected $casts = [
        'context' => 'array',
        'headers' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}


