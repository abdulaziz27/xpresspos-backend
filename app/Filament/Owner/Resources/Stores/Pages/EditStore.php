<?php

namespace App\Filament\Owner\Resources\Stores\Pages;

use App\Filament\Owner\Resources\Stores\StoreResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Hapus'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

