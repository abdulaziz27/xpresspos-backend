<?php

namespace App\Filament\Owner\Resources\Recipes\Pages;

use App\Filament\Owner\Resources\Recipes;
use Filament\Resources\Pages\CreateRecord;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = Recipes\RecipeResource::class;
}
