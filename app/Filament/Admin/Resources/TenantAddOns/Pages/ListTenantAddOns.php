<?php

namespace App\Filament\Admin\Resources\TenantAddOns\Pages;

use App\Filament\Admin\Resources\TenantAddOns\TenantAddOnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantAddOns extends ListRecords
{
    protected static string $resource = TenantAddOnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
