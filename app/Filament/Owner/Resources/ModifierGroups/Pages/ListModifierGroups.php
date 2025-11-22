<?php

namespace App\Filament\Owner\Resources\ModifierGroups\Pages;

use App\Filament\Owner\Resources\ModifierGroups\ModifierGroupResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListModifierGroups extends ListRecords
{
    protected static string $resource = ModifierGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

