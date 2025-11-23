<?php

namespace App\Filament\Owner\Resources\Stores\Pages;

use App\Filament\Owner\Resources\Stores\StoreResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set (backup to model boot() event)
        $user = auth()->user();
        if ($user && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $user->currentTenant()?->id;
        }

        return $data;
    }
}

