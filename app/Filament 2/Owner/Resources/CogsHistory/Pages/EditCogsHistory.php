<?php

namespace App\Filament\Owner\Resources\CogsHistory\Pages;

use App\Filament\Owner\Resources\CogsHistory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCogsHistory extends EditRecord
{
    protected static string $resource = CogsHistory\CogsHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Hapus'),
        ];
    }
}
