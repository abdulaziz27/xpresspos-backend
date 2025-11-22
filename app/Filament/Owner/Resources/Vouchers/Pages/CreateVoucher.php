<?php

namespace App\Filament\Owner\Resources\Vouchers\Pages;

use App\Filament\Owner\Resources\Vouchers\VoucherResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure tenant_id is set (backup to model booted() event)
        $user = auth()->user();
        if ($user && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $user->currentTenant()?->id;
        }

        // Initialize redemptions_count to 0
        if (!isset($data['redemptions_count'])) {
            $data['redemptions_count'] = 0;
        }

        return $data;
    }
}


