<?php

namespace App\Filament\Owner\Resources\Staff\Pages;

use App\Filament\Owner\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Password is already hashed in form
        return $data;
    }

    protected function afterCreate(): void
    {
        // Auto-create user_tenant_access entry for current tenant
        $user = $this->record;
        $currentTenant = auth()->user()?->currentTenant();

        if ($currentTenant) {
            // Check if access already exists
            $exists = DB::table('user_tenant_access')
                ->where('user_id', $user->id)
                ->where('tenant_id', $currentTenant->id)
                ->exists();

            if (!$exists) {
                DB::table('user_tenant_access')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'tenant_id' => $currentTenant->id,
                    'role' => 'staff', // Default role for new staff
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Staff berhasil ditambahkan');
    }
}

