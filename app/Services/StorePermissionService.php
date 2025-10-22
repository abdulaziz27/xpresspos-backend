<?php

namespace App\Services;

use App\Enums\AssignmentRoleEnum;
use App\Models\User;
use App\Models\Store;
use App\Models\StoreUserAssignment;
use App\Models\PermissionAuditLog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class StorePermissionService
{
    /**
     * Check if user has permission for a specific action in a store.
     */
    public function hasPermission(User $user, string $storeId, string $permission): bool
    {
        // Cache key for this permission check
        $cacheKey = "store_permission:{$user->id}:{$storeId}:{$permission}";
        
        return cache()->remember($cacheKey, 300, function () use ($user, $storeId, $permission) {
            // Admin sistem has all permissions globally
            if ($user->hasRole('admin_sistem')) {
                return true;
            }

            // Check if user has role-based permissions in this store
            $userRoles = $user->roles()->where('roles.store_id', $storeId)->get();
            foreach ($userRoles as $role) {
                if ($role->hasPermissionTo($permission)) {
                    return true;
                }
            }
            
            // Check if user has direct permission in this store
            $hasDirectPermission = $user->permissions()
                ->where('name', $permission)
                ->wherePivot('store_id', $storeId)
                ->exists();
                
            if ($hasDirectPermission) {
                return true;
            }

            // Fallback to legacy assignment system for backward compatibility
            $assignment = $this->getUserStoreAssignment($user, $storeId);
            
            if (!$assignment) {
                return false;
            }

            return $this->roleHasPermission($assignment->assignment_role, $permission);
        });
    }

    /**
     * Get user's assignment for a specific store.
     */
    public function getUserStoreAssignment(User $user, string $storeId): ?StoreUserAssignment
    {
        return $user->storeAssignments()
            ->where('store_id', $storeId)
            ->first();
    }

    /**
     * Check if a role has a specific permission.
     */
    public function roleHasPermission(AssignmentRoleEnum $role, string $permission): bool
    {
        $permissions = $this->getRolePermissions($role);
        
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Get all permissions for a role.
     */
    public function getRolePermissions(AssignmentRoleEnum $role): array
    {
        return match ($role) {
            AssignmentRoleEnum::OWNER => ['*'], // All permissions
            AssignmentRoleEnum::ADMIN => [
                'products.*',
                'orders.*',
                'inventory.*',
                'reports.*',
                'staff.view',
                'staff.create',
                'staff.update',
                'members.*',
                'tables.*',
                'categories.*',
                'discounts.*',
                'payments.*',
            ],
            AssignmentRoleEnum::MANAGER => [
                'products.view',
                'products.create',
                'products.update',
                'orders.*',
                'inventory.view',
                'inventory.update',
                'reports.view',
                'staff.view',
                'members.*',
                'tables.*',
                'categories.view',
                'discounts.view',
                'payments.view',
                'payments.create',
            ],
            AssignmentRoleEnum::STAFF => [
                'products.view',
                'orders.view',
                'orders.create',
                'orders.update',
                'inventory.view',
                'members.view',
                'members.create',
                'tables.view',
                'tables.update',
                'payments.view',
                'payments.create',
            ],
        };
    }

    /**
     * Get all stores where user has a specific permission.
     */
    public function getStoresWithPermission(User $user, string $permission): array
    {
        if ($user->hasRole('admin_sistem')) {
            return Store::pluck('id')->toArray();
        }

        return $user->storeAssignments()
            ->get()
            ->filter(function ($assignment) use ($permission) {
                return $this->roleHasPermission($assignment->assignment_role, $permission);
            })
            ->pluck('store_id')
            ->toArray();
    }

    /**
     * Check if user can manage another user in a store.
     */
    public function canManageUser(User $manager, User $targetUser, string $storeId): bool
    {
        if ($manager->hasRole('admin_sistem')) {
            return true;
        }

        $managerAssignment = $this->getUserStoreAssignment($manager, $storeId);
        $targetAssignment = $this->getUserStoreAssignment($targetUser, $storeId);

        if (!$managerAssignment || !$targetAssignment) {
            return false;
        }

        return $managerAssignment->canManage($targetAssignment);
    }

    /**
     * Get user's highest role in a store.
     */
    public function getUserHighestRole(User $user, string $storeId): ?AssignmentRoleEnum
    {
        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->assignment_role;
    }

    /**
     * Check if user has management access to a store.
     */
    public function hasManagementAccess(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->hasManagementPermissions() ?? false;
    }

    /**
     * Check if user has admin access to a store.
     */
    public function hasAdminAccess(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->hasAdminPermissions() ?? false;
    }

    /**
     * Check if user owns a store.
     */
    public function ownsStore(User $user, string $storeId): bool
    {
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        $assignment = $this->getUserStoreAssignment($user, $storeId);
        
        return $assignment?->hasOwnerPermissions() ?? false;
    }

    /**
     * Grant permission to user in a specific store.
     */
    public function grantPermissionToUser(User $user, string $storeId, string $permission, ?User $changedBy = null): bool
    {
        try {
            // Verify permission exists
            if (!Permission::where('name', $permission)->exists()) {
                return false;
            }

            $user->givePermissionTo($permission, $storeId);
            
            // Invalidate cache for this user and store
            $this->invalidateUserPermissionCache($user->id, $storeId);
            
            // Log the change
            $this->logPermissionChange($storeId, $user->id, 'granted', $permission, null, $permission, $changedBy);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke permission from user in a specific store.
     */
    public function revokePermissionFromUser(User $user, string $storeId, string $permission, ?User $changedBy = null): bool
    {
        try {
            $user->revokePermissionTo($permission, $storeId);
            
            // Invalidate cache for this user and store
            $this->invalidateUserPermissionCache($user->id, $storeId);
            
            // Log the change
            $this->logPermissionChange($storeId, $user->id, 'revoked', $permission, $permission, null, $changedBy);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Assign role to user in a specific store.
     */
    public function assignRoleInStore(User $user, string $storeId, string $roleName): bool
    {
        try {
            // Find role for this store
            $role = Role::where('name', $roleName)
                ->where('store_id', $storeId)
                ->first();

            if (!$role) {
                return false;
            }

            $user->assignRole($role);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reset user permissions to role defaults in a store.
     */
    public function resetUserToRoleDefaults(User $user, string $storeId, string $roleName, ?User $changedBy = null): bool
    {
        try {
            // Remove all custom permissions for this store
            $permissions = Permission::all();
            foreach ($permissions as $permission) {
                $user->revokePermissionTo($permission->name, $storeId);
            }

            // Assign role which will give default permissions
            $result = $this->assignRoleInStore($user, $storeId, $roleName);
            
            if ($result) {
                // Invalidate cache for this user and store
                $this->invalidateUserPermissionCache($user->id, $storeId);
                
                // Log the reset action
                $this->logPermissionChange($storeId, $user->id, 'reset_to_default', null, 'custom_permissions', $roleName, $changedBy);
            }
            
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all permissions for a user in a store (role + custom permissions).
     */
    public function getEffectivePermissions(User $user, string $storeId): array
    {
        $permissions = [];

        // Get permissions from roles in this store
        $roles = $user->roles()->where('roles.store_id', $storeId)->get();
        foreach ($roles as $role) {
            $rolePermissions = $role->permissions->pluck('name')->toArray();
            $permissions = array_merge($permissions, $rolePermissions);
        }

        // Get direct permissions in this store
        $directPermissions = $user->permissions()
            ->wherePivot('store_id', $storeId)
            ->pluck('name')
            ->toArray();
        
        $permissions = array_merge($permissions, $directPermissions);

        return array_unique($permissions);
    }

    /**
     * Get permissions grouped by category for UI.
     */
    public function getPermissionsByCategory(): array
    {
        return [
            'products' => [
                'products.view' => 'Lihat Produk',
                'products.create' => 'Tambah Produk',
                'products.update' => 'Edit Produk',
                'products.delete' => 'Hapus Produk',
            ],
            'orders' => [
                'orders.view' => 'Lihat Pesanan',
                'orders.create' => 'Buat Pesanan',
                'orders.update' => 'Edit Pesanan',
                'orders.cancel' => 'Batalkan Pesanan',
                'orders.complete' => 'Selesaikan Pesanan',
            ],
            'inventory' => [
                'inventory.view' => 'Lihat Stok',
                'inventory.update' => 'Update Stok',
                'inventory.reports' => 'Laporan Stok',
            ],
            'reports' => [
                'reports.view' => 'Lihat Laporan',
                'reports.sales' => 'Laporan Penjualan',
                'reports.financial' => 'Laporan Keuangan',
                'reports.analytics' => 'Analytics',
            ],
            'staff' => [
                'staff.view' => 'Lihat Staff',
                'staff.create' => 'Tambah Staff',
                'staff.update' => 'Edit Staff',
                'staff.manage' => 'Kelola Staff',
                'staff.permissions' => 'Kelola Permission Staff',
            ],
            'members' => [
                'members.view' => 'Lihat Member',
                'members.create' => 'Tambah Member',
                'members.update' => 'Edit Member',
            ],
            'tables' => [
                'tables.view' => 'Lihat Meja',
                'tables.update' => 'Update Meja',
                'tables.manage' => 'Kelola Meja',
                'tables.occupy' => 'Atur Okupansi Meja',
            ],
            'payments' => [
                'payments.view' => 'Lihat Pembayaran',
                'payments.create' => 'Proses Pembayaran',
                'payments.refund' => 'Refund',
                'payments.view_history' => 'Lihat Riwayat Pembayaran',
            ],
            'categories' => [
                'categories.view' => 'Lihat Kategori',
                'categories.create' => 'Tambah Kategori',
                'categories.update' => 'Edit Kategori',
                'categories.delete' => 'Hapus Kategori',
            ],
            'discounts' => [
                'discounts.view' => 'Lihat Diskon',
                'discounts.create' => 'Tambah Diskon',
                'discounts.update' => 'Edit Diskon',
                'discounts.delete' => 'Hapus Diskon',
            ],
        ];
    }

    /**
     * Log permission changes for audit trail.
     */
    protected function logPermissionChange(
        string $storeId, 
        string $userId, 
        string $action, 
        ?string $permission = null, 
        ?string $oldValue = null, 
        ?string $newValue = null, 
        ?User $changedBy = null
    ): void {
        PermissionAuditLog::create([
            'store_id' => $storeId,
            'user_id' => $userId,
            'changed_by' => $changedBy?->id ?? auth()->id(),
            'action' => $action,
            'permission' => $permission,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /**
     * Get audit trail for a user in a store.
     */
    public function getAuditTrail(string $userId, string $storeId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return PermissionAuditLog::where('user_id', $userId)
            ->where('store_id', $storeId)
            ->with(['changedBy'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Invalidate permission cache for a user in a store.
     */
    protected function invalidateUserPermissionCache(string $userId, string $storeId): void
    {
        // Get all possible permissions to clear cache
        $permissions = Permission::pluck('name');
        
        foreach ($permissions as $permission) {
            $cacheKey = "store_permission:{$userId}:{$storeId}:{$permission}";
            cache()->forget($cacheKey);
        }
        
        // Also clear Spatie Permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Warm up permission cache for a user in a store.
     */
    public function warmUpPermissionCache(User $user, string $storeId): void
    {
        $permissions = Permission::pluck('name');
        
        foreach ($permissions as $permission) {
            $this->hasPermission($user, $storeId, $permission);
        }
    }
}