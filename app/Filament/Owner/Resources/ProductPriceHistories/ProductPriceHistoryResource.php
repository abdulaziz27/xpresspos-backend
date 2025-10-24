<?php

namespace App\Filament\Owner\Resources\ProductPriceHistories;

use App\Filament\Owner\Resources\ProductPriceHistories\Pages\ListProductPriceHistories;
use App\Filament\Owner\Resources\ProductPriceHistories\Tables\ProductPriceHistoriesTable;
use App\Models\ProductPriceHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductPriceHistoryResource extends Resource
{
    protected static ?string $model = ProductPriceHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Price History';

    protected static ?string $modelLabel = 'Product Price History';

    protected static ?string $pluralModelLabel = 'Product Price Histories';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return ProductPriceHistoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductPriceHistories::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Price history records are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // Price history records should not be editable
    }

    public static function canDelete($record): bool
    {
        return false; // Price history records should not be deletable
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
        // Hide from navigation for MVP - audit feature, not daily operations
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->store_id) {
            setPermissionsTeamId($user->store_id);
        }

        return $user->hasRole('owner') || $user->hasAnyRole(['admin_sistem', 'manager']);
    }
}