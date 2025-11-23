<?php

namespace App\Filament\Owner\Resources\PaymentSecurityLogs\Pages;

use App\Filament\Owner\Resources\PaymentSecurityLogs\PaymentSecurityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentSecurityLogs extends ListRecords
{
    protected static string $resource = PaymentSecurityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}


