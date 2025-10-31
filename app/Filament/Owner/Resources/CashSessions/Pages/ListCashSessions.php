<?php

namespace App\Filament\Owner\Resources\CashSessions\Pages;

use App\Filament\Owner\Resources\CashSessions\CashSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashSessions extends ListRecords
{
    protected static string $resource = CashSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah'),
        ];
    }
}
