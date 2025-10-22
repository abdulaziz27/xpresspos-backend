<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionAuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'store_id',
        'user_id',
        'changed_by',
        'action',
        'permission',
        'old_value',
        'new_value',
        'notes',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'granted' => 'Permission Diberikan',
            'revoked' => 'Permission Dicabut',
            'role_changed' => 'Role Diubah',
            'reset_to_default' => 'Reset ke Default',
            default => $this->action,
        };
    }
}