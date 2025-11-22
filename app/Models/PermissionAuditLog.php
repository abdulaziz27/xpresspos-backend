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
        'tenant_id',
        'store_id',
        'user_id',
        'changed_by',
        'action',
        'permission',
        'old_value',
        'new_value',
        'notes',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($log) {
            // Auto-set tenant_id from user or store
            if (!$log->tenant_id) {
                if ($log->user_id) {
                    $user = User::find($log->user_id);
                    if ($user) {
                        $log->tenant_id = $user->currentTenant()?->id;
                    }
                }
                
                // Fallback to store->tenant_id if user tenant not found
                if (!$log->tenant_id && $log->store_id) {
                    $store = Store::find($log->store_id);
                    if ($store) {
                        $log->tenant_id = $store->tenant_id;
                    }
                }
            }
        });
    }

    /**
     * Get the tenant that owns the permission audit log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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