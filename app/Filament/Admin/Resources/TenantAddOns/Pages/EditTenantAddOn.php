<?php

namespace App\Filament\Admin\Resources\TenantAddOns\Pages;

use App\Filament\Admin\Resources\TenantAddOns\TenantAddOnResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTenantAddOn extends EditRecord
{
    protected static string $resource = TenantAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
