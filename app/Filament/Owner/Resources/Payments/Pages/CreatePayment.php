<?php

namespace App\Filament\Owner\Resources\Payments\Pages;

use App\Filament\Owner\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;
}
