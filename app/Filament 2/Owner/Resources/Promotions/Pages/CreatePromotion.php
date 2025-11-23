<?php

namespace App\Filament\Owner\Resources\Promotions\Pages;

use App\Filament\Owner\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set (backup to model booted() event)
        $user = auth()->user();
        if ($user && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $user->currentTenant()?->id;
        }

        // Convert empty string to null for store_id (optional field)
        if (isset($data['store_id']) && $data['store_id'] === '') {
            $data['store_id'] = null;
        }

        return $data;
    }
}


