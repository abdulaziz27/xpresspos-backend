<?php

namespace App\Filament\Admin\Resources\Stores;

use App\Filament\Admin\Resources\Stores\Pages\CreateStore;
use App\Filament\Admin\Resources\Stores\Pages\EditStore;
use App\Filament\Admin\Resources\Stores\Pages\ListStores;
use App\Filament\Admin\Resources\Stores\Schemas\StoreForm;
use App\Filament\Admin\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Stores';

    protected static ?string $modelLabel = 'Store';

    protected static ?string $pluralModelLabel = 'Stores';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    /**
     * Admin panel: Admin can create stores and assign to tenants.
     */
    public static function canCreate(): bool
    {
        return true; // Admin bisa create store
    }

    public static function canEdit($record): bool
    {
        // Admin bisa edit terbatas (hanya status, dll)
        return true;
    }

    public static function canDelete($record): bool
    {
        // Admin tidak bisa delete store
        return false;
    }


    public static function form(Schema $schema): Schema
    {
        return StoreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
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
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }
}
