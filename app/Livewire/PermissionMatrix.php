<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\StorePermissionService;
use App\Models\User;

class PermissionMatrix extends Component
{
    public $userId;
    public $storeId;
    public $selectedRole = 'staff';
    public $permissions = [];
    public $useDefaultPermissions = true;
    
    protected $listeners = ['roleChanged' => 'handleRoleChange'];

    public function mount($userId = null, $storeId = null, $role = 'staff')
    {
        $this->userId = $userId;
        $this->storeId = $storeId;
        $this->selectedRole = $role;
        
        $this->loadPermissions();
    }

    public function loadPermissions()
    {
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        
        // Initialize permissions array
        foreach ($categories as $category => $categoryPermissions) {
            $this->permissions[$category] = [];
        }
        
        // Load current user permissions if editing
        if ($this->userId && $this->storeId) {
            $user = User::find($this->userId);
            if ($user) {
                $userPermissions = $permissionService->getEffectivePermissions($user, $this->storeId);
                
                foreach ($categories as $category => $categoryPermissions) {
                    $this->permissions[$category] = array_intersect(
                        array_keys($categoryPermissions), 
                        $userPermissions
                    );
                }
            }
        } else {
            // Load default permissions for role
            $this->loadDefaultPermissions();
        }
    }

    public function loadDefaultPermissions()
    {
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        
        // Get default permissions based on role
        $defaultPermissions = $this->getDefaultPermissionsForRole($this->selectedRole);
        
        foreach ($categories as $category => $categoryPermissions) {
            $this->permissions[$category] = [];
            
            foreach ($categoryPermissions as $permission => $label) {
                if (in_array($permission, $defaultPermissions)) {
                    $this->permissions[$category][] = $permission;
                }
            }
        }
    }

    public function handleRoleChange($role)
    {
        $this->selectedRole = $role;
        if ($this->useDefaultPermissions) {
            $this->loadDefaultPermissions();
        }
    }

    public function toggleUseDefault()
    {
        $this->useDefaultPermissions = !$this->useDefaultPermissions;
        
        if ($this->useDefaultPermissions) {
            $this->loadDefaultPermissions();
        }
    }

    public function resetToDefault()
    {
        $this->useDefaultPermissions = true;
        $this->loadDefaultPermissions();
    }

    public function selectAllInCategory($category)
    {
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        
        if (isset($categories[$category])) {
            $this->permissions[$category] = array_keys($categories[$category]);
        }
    }

    public function deselectAllInCategory($category)
    {
        $this->permissions[$category] = [];
    }

    private function getDefaultPermissionsForRole($role)
    {
        return match ($role) {
            'owner' => ['*'], // All permissions
            'admin' => [
                'products.view', 'products.create', 'products.update', 'products.delete',
                'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.complete',
                'inventory.view', 'inventory.update', 'inventory.reports',
                'reports.view', 'reports.sales', 'reports.financial', 'reports.analytics',
                'staff.view', 'staff.create', 'staff.update',
                'members.view', 'members.create', 'members.update',
                'tables.view', 'tables.update', 'tables.manage', 'tables.occupy',
                'payments.view', 'payments.create', 'payments.refund', 'payments.view_history',
                'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                'discounts.view', 'discounts.create', 'discounts.update', 'discounts.delete',
            ],
            'manager' => [
                'products.view', 'products.create', 'products.update',
                'orders.view', 'orders.create', 'orders.update', 'orders.complete',
                'inventory.view', 'inventory.update',
                'reports.view',
                'staff.view',
                'members.view', 'members.create', 'members.update',
                'tables.view', 'tables.update',
                'categories.view',
                'discounts.view',
                'payments.view', 'payments.create',
            ],
            'staff' => [
                'products.view',
                'orders.view', 'orders.create', 'orders.update',
                'inventory.view',
                'members.view', 'members.create',
                'tables.view', 'tables.update',
                'payments.view', 'payments.create',
            ],
            default => [],
        };
    }

    public function render()
    {
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        
        return view('livewire.permission-matrix', [
            'categories' => $categories,
        ]);
    }
}