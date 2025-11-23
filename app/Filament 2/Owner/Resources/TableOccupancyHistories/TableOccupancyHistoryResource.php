<?php

namespace App\Filament\Owner\Resources\TableOccupancyHistories;

use App\Filament\Owner\Resources\TableOccupancyHistories\Pages\ListTableOccupancyHistories;
use App\Filament\Owner\Resources\TableOccupancyHistories\Tables\TableOccupancyHistoriesTable;
use App\Models\TableOccupancyHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TableOccupancyHistoryResource extends Resource
{
    protected static ?string $model = TableOccupancyHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Table History';

    protected static ?string $modelLabel = 'Table Occupancy History';

    protected static ?string $pluralModelLabel = 'Table Occupancy Histories';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return TableOccupancyHistoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTableOccupancyHistories::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // History records are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // History records should not be editable
    }

    public static function canDelete($record): bool
    {
        return false; // History records should not be deletable
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        
        if ($user && $user->store_id) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->where('store_id', $user->store_id);
        }

        return parent::getEloquentQuery();
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from navigation for MVP - too detailed for initial release
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}