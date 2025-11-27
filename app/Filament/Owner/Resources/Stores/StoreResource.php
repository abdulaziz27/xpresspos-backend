<?php

namespace App\Filament\Owner\Resources\Stores;

use App\Filament\Owner\Resources\Stores\Pages\CreateStore;
use App\Filament\Owner\Resources\Stores\Pages\EditStore;
use App\Filament\Owner\Resources\Stores\Pages\ListStores;
use App\Filament\Owner\Resources\Stores\RelationManagers\StoreUserAssignmentsRelationManager;
use App\Filament\Owner\Resources\Stores\Schemas\StoreForm;
use App\Filament\Owner\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Toko';

    protected static ?string $modelLabel = 'Toko';

    protected static ?string $pluralModelLabel = 'Toko';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Toko & Tim';

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
            StoreUserAssignmentsRelationManager::class,
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

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('create', static::$model);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // Check permission first
        if (!Gate::forUser($user)->allows('delete', $record)) {
            return false;
        }

        // Prevent deletion if store has related data
        $hasOrders = $record->orders()->exists();
        $hasProducts = $record->products()->exists();
        $hasMembers = $record->members()->exists();
        $hasUserAssignments = $record->userAssignments()->exists();

        // Only allow deletion if store has no related data
        return !($hasOrders || $hasProducts || $hasMembers || $hasUserAssignments);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Filter by current tenant
        $user = auth()->user();
        if ($user) {
            $tenantId = $user->currentTenant()?->id;
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        }

        return $query;
    }
}

