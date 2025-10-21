<?php

namespace App\Enums;

enum AssignmentRoleEnum: string
{
    case STAFF = 'staff';
    case MANAGER = 'manager';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    /**
     * Get display name for the role.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::STAFF => 'Staff',
            self::MANAGER => 'Manager',
            self::ADMIN => 'Administrator',
            self::OWNER => 'Store Owner',
        };
    }

    /**
     * Get all role values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all roles with display names.
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($role) {
            return [$role->value => $role->getDisplayName()];
        })->toArray();
    }

    /**
     * Check if role has management permissions.
     */
    public function hasManagementPermissions(): bool
    {
        return in_array($this, [self::MANAGER, self::ADMIN, self::OWNER]);
    }

    /**
     * Check if role has admin permissions.
     */
    public function hasAdminPermissions(): bool
    {
        return in_array($this, [self::ADMIN, self::OWNER]);
    }

    /**
     * Check if role has owner permissions.
     */
    public function hasOwnerPermissions(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Get role hierarchy level (higher number = more permissions).
     */
    public function getHierarchyLevel(): int
    {
        return match ($this) {
            self::STAFF => 1,
            self::MANAGER => 2,
            self::ADMIN => 3,
            self::OWNER => 4,
        };
    }

    /**
     * Check if this role can manage another role.
     */
    public function canManage(AssignmentRoleEnum $otherRole): bool
    {
        return $this->getHierarchyLevel() > $otherRole->getHierarchyLevel();
    }
}