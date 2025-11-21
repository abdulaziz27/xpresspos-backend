<?php

namespace App\Filament\Owner\Resources\PermissionAuditLogs\Pages;

use App\Filament\Owner\Resources\PermissionAuditLogs\PermissionAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissionAuditLogs extends ListRecords
{
    protected static string $resource = PermissionAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}


