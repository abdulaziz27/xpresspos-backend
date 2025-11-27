<?php

namespace App\Filament\Owner\Resources\ModifierGroups;

use App\Filament\Owner\Resources\ModifierGroups\Pages\CreateModifierGroup;
use App\Filament\Owner\Resources\ModifierGroups\Pages\EditModifierGroup;
use App\Filament\Owner\Resources\ModifierGroups\Pages\ListModifierGroups;
use App\Filament\Owner\Resources\ModifierGroups\RelationManagers\ModifierItemsRelationManager;
use App\Filament\Owner\Resources\ModifierGroups\Schemas\ModifierGroupForm;
use App\Filament\Owner\Resources\ModifierGroups\Tables\ModifierGroupsTable;
use App\Models\ModifierGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ModifierGroupResource extends Resource
{
    protected static ?string $model = ModifierGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Modifier';

    protected static ?string $modelLabel = 'Modifier';

    protected static ?string $pluralModelLabel = 'Modifier';

    protected static ?int $navigationSort = 13;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk';

    public static function form(Schema $schema): Schema
    {
        return ModifierGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModifierGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ModifierItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModifierGroups::route('/'),
            'create' => CreateModifierGroup::route('/create'),
            'edit' => EditModifierGroup::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', static::$model);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('delete', $record);
    }
}

