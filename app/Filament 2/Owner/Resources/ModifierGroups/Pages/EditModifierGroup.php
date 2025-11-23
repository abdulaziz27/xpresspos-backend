<?php

namespace App\Filament\Owner\Resources\ModifierGroups\Pages;

use App\Filament\Owner\Resources\ModifierGroups\ModifierGroupResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditModifierGroup extends EditRecord
{
    protected static string $resource = ModifierGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

