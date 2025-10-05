<?php

namespace App\Filament\Owner\Resources\Tables\Pages;

use App\Filament\Owner\Resources\Tables\TableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTables extends ListRecords
{
    protected static string $resource = TableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
