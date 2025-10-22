<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Pages;

use App\Filament\Owner\Resources\StoreUserAssignments\StoreUserAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use App\Services\StorePermissionService;
use Filament\Notifications\Notification;

class EditStoreUserAssignment extends EditRecord
{
    protected static string $resource = StoreUserAssignmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load current permissions for the user in this store
        $permissionService = app(StorePermissionService::class);
        $permissions = $permissionService->getEffectivePermissions(
            $this->record->user, 
            $this->record->store_id
        );

        // Group permissions by category
        $categorizedPermissions = [];
        $categories = $permissionService->getPermissionsByCategory();
        
        foreach ($categories as $category => $categoryPermissions) {
            $categorizedPermissions[$category] = array_intersect(
                array_keys($categoryPermissions), 
                $permissions
            );
        }

        $data['permissions'] = $categorizedPermissions;
        $data['user'] = [
            'name' => $this->record->user->name,
            'email' => $this->record->user->email,
        ];

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Update user information
        $record->user->update([
            'name' => $data['user']['name'],
            'email' => $data['user']['email'],
        ]);

        // Update assignment
        $record->update([
            'assignment_role' => $data['assignment_role'],
            'is_primary' => $data['is_primary'] ?? false,
        ]);

        // Update role in Spatie Permission
        $permissionService = app(StorePermissionService::class);
        
        // Remove old role and assign new one
        $permissionService->resetUserToRoleDefaults(
            $record->user, 
            $record->store_id, 
            $data['assignment_role']
        );

        // Handle custom permissions
        if (isset($data['permissions'])) {
            $this->handlePermissions($record->user, $record->store_id, $data['permissions']);
        }

        return $record;
    }

    protected function handlePermissions($user, string $storeId, array $permissions): void
    {
        $permissionService = app(StorePermissionService::class);
        
        foreach ($permissions as $category => $categoryPermissions) {
            if (is_array($categoryPermissions)) {
                foreach ($categoryPermissions as $permission) {
                    $permissionService->grantPermissionToUser($user, $storeId, $permission);
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_permissions')
                ->label('Reset ke Default')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $permissionService = app(StorePermissionService::class);
                    $permissionService->resetUserToRoleDefaults(
                        $this->record->user,
                        $this->record->store_id,
                        $this->record->assignment_role->value
                    );
                    
                    Notification::make()
                        ->title('Permissions berhasil direset ke default')
                        ->success()
                        ->send();
                        
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
