<?php

namespace App\Filament\Owner\Resources\Tables;

use App\Filament\Owner\Resources\Tables\Pages\CreateTable;
use App\Filament\Owner\Resources\Tables\Pages\EditTable;
use App\Filament\Owner\Resources\Tables\Pages\ListTables;
use App\Filament\Owner\Resources\Tables\Schemas\TableForm;
use App\Filament\Owner\Resources\Tables\Tables\TablesTable;
use App\Models\Table;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table as FilamentTable;

class TableResource extends Resource
{
    protected static ?string $model = Table::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Tables';

    protected static ?string $modelLabel = 'Table';

    protected static ?string $pluralModelLabel = 'Tables';

    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return TableForm::configure($schema);
    }

    public static function table(FilamentTable $table): FilamentTable
    {
        return TablesTable::configure($table);
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
            'index' => ListTables::route('/'),
            'create' => CreateTable::route('/create'),
            'edit' => EditTable::route('/{record}/edit'),
        ];
    }
}
