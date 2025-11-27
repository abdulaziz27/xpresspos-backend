<?php

namespace App\Filament\Owner\Resources\Tables;

use App\Filament\Owner\Resources\Tables\Pages\CreateTable;
use App\Filament\Owner\Resources\Tables\Pages\EditTable;
use App\Filament\Owner\Resources\Tables\Pages\ListTables;
use App\Filament\Owner\Resources\Tables\Schemas\TableForm;
use App\Filament\Owner\Resources\Tables\Tables\TablesTable;
use App\Models\Table;
use App\Models\Store;
use App\Models\Scopes\StoreScope;
use App\Enums\AssignmentRoleEnum;
use BackedEnum;
use App\Filament\Traits\HasPlanBasedNavigation;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class TableResource extends Resource
{
    use HasPlanBasedNavigation;
    protected static ?string $model = Table::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Meja';

    protected static ?string $modelLabel = 'Meja';

    protected static ?string $pluralModelLabel = 'Meja';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional Harian';




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
            \App\Filament\Owner\Resources\Tables\RelationManagers\TableOccupancyHistoriesRelationManager::class,
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

    public static function shouldRegisterNavigation(): bool
    {
        // Hide navigation if tenant doesn't have table management feature
        return static::hasPlanFeature('ALLOW_TABLE_MANAGEMENT');
    }

    public static function canViewAny(): bool
    {
        return static::hasPlanFeature('ALLOW_TABLE_MANAGEMENT');
    }

    public static function canCreate(): bool
    {
        if (!static::hasPlanFeature('ALLOW_TABLE_MANAGEMENT')) {
            return false;
        }

        $user = auth()->user();
        return (bool) $user && Gate::forUser($user)->allows('create', static::$model);
    }

    public static function getEloquentQuery(): Builder
    {
        // Remove StoreScope to show tables from all stores in tenant
        $query = parent::getEloquentQuery()
            ->withoutGlobalScope(StoreScope::class)
            ->with(['store', 'currentOrder']);

        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;
        
        if (!$tenantId) {
            return $query->whereRaw('1 = 0');
        }

        // Check if user is owner (has owner role or owner assignment)
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;

        if ($isOwner) {
            // Owner can see tables from all stores in tenant
            $storeIds = Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
            
            if (!empty($storeIds)) {
                $query->whereIn('store_id', $storeIds);
            }
        } else {
            // Staff/User can only see tables from stores they are assigned to
            $assignedStoreIds = $user->stores()->pluck('stores.id')->toArray();
            
            if (empty($assignedStoreIds)) {
                // No store assignment, return empty query
                return $query->whereRaw('1 = 0');
            }

            $query->whereIn('store_id', $assignedStoreIds);
        }

        return $query;
    }
}
