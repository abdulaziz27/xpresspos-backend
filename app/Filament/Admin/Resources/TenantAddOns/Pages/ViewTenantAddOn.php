<?php

namespace App\Filament\Admin\Resources\TenantAddOns\Pages;

use App\Filament\Admin\Resources\TenantAddOns\TenantAddOnResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTenantAddOn extends ViewRecord
{
    protected static string $resource = TenantAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
