<?php

namespace App\Filament\Owner\Resources\Roles\Pages;

use App\Filament\Owner\Resources\Roles\RoleResource;
use App\Services\StorePermissionService;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load current permissions for the role
        $permissionService = app(StorePermissionService::class);
        $categories = $permissionService->getPermissionsByCategory();
        
        $rolePermissions = $this->record->permissions->pluck('name')->toArray();
        
        // Group permissions by category
        $categorizedPermissions = [];
        foreach ($categories as $category => $categoryPermissions) {
            $categorizedPermissions[$category] = array_intersect(
                array_keys($categoryPermissions),
                $rolePermissions
            );
        }

        $data['permissions'] = $categorizedPermissions;
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Don't save permissions here, handle in afterSave
        unset($data['permissions']);
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync permissions if provided
        if (isset($this->data['permissions'])) {
            // Flatten categorized permissions
            $permissions = [];
            foreach ($this->data['permissions'] as $category => $categoryPermissions) {
                $permissions = array_merge($permissions, $categoryPermissions);
            }
            
            // Get permission IDs from names
            $permissionIds = \Spatie\Permission\Models\Permission::whereIn('name', $permissions)
                ->pluck('id')
                ->toArray();
            
            $this->record->syncPermissions($permissionIds);
        }
    }
}

