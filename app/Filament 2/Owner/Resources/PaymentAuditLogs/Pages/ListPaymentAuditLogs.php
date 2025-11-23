<?php

namespace App\Filament\Owner\Resources\PaymentAuditLogs\Pages;

use App\Filament\Owner\Resources\PaymentAuditLogs\PaymentAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentAuditLogs extends ListRecords
{
    protected static string $resource = PaymentAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}


