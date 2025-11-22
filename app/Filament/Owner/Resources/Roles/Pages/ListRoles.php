<?php

namespace App\Filament\Owner\Resources\Roles\Pages;

use App\Filament\Owner\Resources\Roles\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        // Owner tidak bisa membuat role baru, hanya bisa edit permissions
        return [];
    }

    public function mount(): void
    {
        parent::mount();
        
        // Ensure default roles exist for current tenant
        RoleResource::ensureDefaultRoles();
    }
}

