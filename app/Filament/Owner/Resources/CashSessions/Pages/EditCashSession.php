<?php

namespace App\Filament\Owner\Resources\CashSessions\Pages;

use App\Filament\Owner\Resources\CashSessions\CashSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCashSession extends EditRecord
{
    protected static string $resource = CashSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
