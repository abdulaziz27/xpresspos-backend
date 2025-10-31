<?php

namespace App\Filament\Owner\Resources\Recipes\Pages;

use App\Filament\Owner\Resources\Recipes;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecipe extends EditRecord
{
    protected static string $resource = Recipes\RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('Hapus'),
        ];
    }
}
