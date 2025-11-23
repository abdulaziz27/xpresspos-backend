<?php

namespace App\Filament\Owner\Resources\Tables\Pages;

use App\Filament\Owner\Resources\Tables\TableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTable extends EditRecord
{
    protected static string $resource = TableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Hapus'),
        ];
    }
}
