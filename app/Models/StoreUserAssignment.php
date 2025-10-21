<?php

namespace App\Models;

use App\Enums\AssignmentRoleEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreUserAssignment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'store_user_assignments';

    protected $fillable = [
        'store_id',
        'user_id',
        'assignment_role',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'assignment_role' => AssignmentRoleEnum::class,
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
    /**
     * Get the display name for the assignment role.
     */
    public function getRoleDisplayNameAttribute(): string
    {
        return $this->assignment_role->getDisplayName();
    }

    /**
     * Check if this assignment has management permissions.
     */
    public function hasManagementPermissions(): bool
    {
        return $this->assignment_role->hasManagementPermissions();
    }

    /**
     * Check if this assignment has admin permissions.
     */
    public function hasAdminPermissions(): bool
    {
        return $this->assignment_role->hasAdminPermissions();
    }

    /**
     * Check if this assignment has owner permissions.
     */
    public function hasOwnerPermissions(): bool
    {
        return $this->assignment_role->hasOwnerPermissions();
    }

    /**
     * Check if this assignment can manage another assignment.
     */
    public function canManage(StoreUserAssignment $otherAssignment): bool
    {
        return $this->assignment_role->canManage($otherAssignment->assignment_role);
    }

    /**
     * Scope to filter by role.
     */
    public function scopeByRole($query, AssignmentRoleEnum $role)
    {
        return $query->where('assignment_role', $role->value);
    }

    /**
     * Scope to get primary assignments.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get management level assignments.
     */
    public function scopeManagement($query)
    {
        return $query->whereIn('assignment_role', [
            AssignmentRoleEnum::MANAGER->value,
            AssignmentRoleEnum::ADMIN->value,
            AssignmentRoleEnum::OWNER->value,
        ]);
    }