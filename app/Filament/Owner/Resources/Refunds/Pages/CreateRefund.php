<?php

namespace App\Filament\Owner\Resources\Refunds\Pages;

use App\Filament\Owner\Resources\Refunds\RefundResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRefund extends CreateRecord
{
    protected static string $resource = RefundResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['store_id'] = $user ? $user->store_id : null;
        $data['user_id'] = $user ? $user->id : null;
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}