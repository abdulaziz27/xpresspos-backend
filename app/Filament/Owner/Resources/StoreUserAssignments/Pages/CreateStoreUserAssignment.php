<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments\Pages;

use App\Filament\Owner\Resources\StoreUserAssignments\StoreUserAssignmentResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\User;
use App\Services\StoreContext;
use App\Services\StorePermissionService;
use Illuminate\Support\Facades\Hash;

class CreateStoreUserAssignment extends CreateRecord
{
    protected static string $resource = StoreUserAssignmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get current store ID
        $storeContext = app(StoreContext::class);
        $currentStoreId = $storeContext->current(auth()->user());
        
        // Set store_id for the assignment
        $data['store_id'] = $currentStoreId;
        
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Create or find user
        $user = User::firstOrCreate(
            ['email' => $data['user']['email']],
            [
                'name' => $data['user']['name'],
                'email' => $data['user']['email'],
                'password' => Hash::make('password'), // Default password
                'store_id' => $data['store_id'],
            ]
        );

        // Create store user assignment
        $assignment = $user->storeAssignments()->create([
            'store_id' => $data['store_id'],
            'assignment_role' => $data['assignment_role'],
            'is_primary' => $data['is_primary'] ?? false,
        ]);

        // Assign role in Spatie Permission
        $permissionService = app(StorePermissionService::class);
        $permissionService->assignRoleInStore($user, $data['store_id'], $data['assignment_role']);

        // Handle custom permissions if any
        if (isset($data['permissions'])) {
            $this->handlePermissions($user, $data['store_id'], $data['permissions']);
        }

        return $assignment;
    }

    protected function handlePermissions(User $user, string $storeId, array $permissions): void
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
