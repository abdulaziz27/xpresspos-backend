<?php

namespace App\Filament\Owner\Resources\Recipes\Pages;

use App\Filament\Owner\Resources\Recipes;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecipes extends ListRecords
{
    protected static string $resource = Recipes\RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
