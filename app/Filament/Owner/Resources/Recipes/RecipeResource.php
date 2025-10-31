<?php

namespace App\Filament\Owner\Resources\Recipes;

use App\Filament\Owner\Resources\Recipes\Pages\CreateRecipe;
use App\Filament\Owner\Resources\Recipes\Pages\EditRecipe;
use App\Filament\Owner\Resources\Recipes\Pages\ListRecipes;
use App\Filament\Owner\Resources\Recipes\Schemas\RecipeForm;
use App\Filament\Owner\Resources\Recipes\Tables\RecipesTable;
use App\Models\Recipe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Resep';

    protected static ?string $modelLabel = 'Resep';

    protected static ?string $pluralModelLabel = 'Resep';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Inventori';

    public static function form(Schema $schema): Schema
    {
        return RecipeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipes::route('/'),
            'create' => CreateRecipe::route('/create'),
            'edit' => EditRecipe::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) $user && Gate::forUser($user)->allows('create', static::$model);
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}
