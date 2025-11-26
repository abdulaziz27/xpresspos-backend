<?php

namespace App\Filament\Admin\Resources\TenantAddOns\Pages;

use App\Filament\Admin\Resources\TenantAddOns\TenantAddOnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantAddOn extends CreateRecord
{
    protected static string $resource = TenantAddOnResource::class;
}
