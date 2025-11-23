<?php

namespace App\Filament\Owner\Resources\CogsHistory\Pages;

use App\Filament\Owner\Resources\CogsHistory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCogsHistory extends ListRecords
{
    protected static string $resource = CogsHistory\CogsHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah'),
        ];
    }
}
