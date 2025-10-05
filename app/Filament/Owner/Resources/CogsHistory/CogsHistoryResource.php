<?php

namespace App\Filament\Owner\Resources\CogsHistory;

use App\Filament\Owner\Resources\CogsHistory\Pages\CreateCogsHistory;
use App\Filament\Owner\Resources\CogsHistory\Pages\EditCogsHistory;
use App\Filament\Owner\Resources\CogsHistory\Pages\ListCogsHistory;
use App\Filament\Owner\Resources\CogsHistory\Schemas\CogsHistoryForm;
use App\Filament\Owner\Resources\CogsHistory\Tables\CogsHistoryTable;
use App\Models\CogsHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CogsHistoryResource extends Resource
{
    protected static ?string $model = CogsHistory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'COGS History';

    protected static ?string $modelLabel = 'COGS Record';

    protected static ?string $pluralModelLabel = 'COGS History';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return CogsHistoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CogsHistoryTable::configure($table);
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
            'index' => ListCogsHistory::route('/'),
            'create' => CreateCogsHistory::route('/create'),
            'edit' => EditCogsHistory::route('/{record}/edit'),
        ];
    }
}
