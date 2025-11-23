<?php

namespace App\Filament\Owner\Resources\Products\Pages;

use App\Filament\Owner\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah'),
        ];
    }

    public function mount(): void
    {
        // Set tenant context for permissions
        $user = auth()->user();
        if ($user) {
            $tenantId = $user->currentTenantId();
            if ($tenantId) {
                setPermissionsTeamId($tenantId);
            }
        }

        parent::mount();
    }
}
