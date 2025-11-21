<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'operation',
        'entity_type',
        'entity_id',
        'user_id',
        'user_email',
        'ip_address',
        'user_agent',
        'old_data',
        'new_data',
        'changes',
        'request_id',
        'session_id',
        'created_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'changes' => 'array',
    ];

    protected $dates = [
        'created_at',
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


