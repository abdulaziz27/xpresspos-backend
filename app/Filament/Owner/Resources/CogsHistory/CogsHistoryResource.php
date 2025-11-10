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

    protected static ?string $navigationLabel = 'Riwayat COGS';

    protected static ?string $modelLabel = 'Data COGS';

    protected static ?string $pluralModelLabel = 'Riwayat COGS';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Inventori';

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
        // Read-only: riwayat COGS adalah hasil perhitungan otomatis, tidak bisa dibuat/diubah manual.
        return [
            'index' => ListCogsHistory::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
