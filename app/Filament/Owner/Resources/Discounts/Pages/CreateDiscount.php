<?php

namespace App\Filament\Owner\Resources\Discounts\Pages;

use App\Filament\Owner\Resources\Discounts\DiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        
        // Auto-fill tenant_id from currentTenant (tidak boleh diinput user)
        if (!$data['tenant_id'] && $user) {
            $tenantId = $user->currentTenant()?->id;
            if ($tenantId) {
                $data['tenant_id'] = $tenantId;
            }
        }
        
        // Convert empty string to null for store_id (optional - null = all stores)
        if (isset($data['store_id']) && $data['store_id'] === '') {
            $data['store_id'] = null;
        }
        
        // Ensure status is set
        if (!isset($data['status'])) {
            $data['status'] = 'active';
        }
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}