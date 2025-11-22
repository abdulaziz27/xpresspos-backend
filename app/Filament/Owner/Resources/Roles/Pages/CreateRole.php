<?php

namespace App\Filament\Owner\Resources\Roles\Pages;

use App\Filament\Owner\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    // CreateRole tidak akan dipanggil karena canCreate() = false
}

