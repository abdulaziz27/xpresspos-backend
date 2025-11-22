<?php

namespace App\Filament\Owner\Resources\Tables;

use App\Filament\Owner\Resources\Tables\Pages\CreateTable;
use App\Filament\Owner\Resources\Tables\Pages\EditTable;
use App\Filament\Owner\Resources\Tables\Pages\ListTables;
use App\Filament\Owner\Resources\Tables\Schemas\TableForm;
use App\Filament\Owner\Resources\Tables\Tables\TablesTable;
use App\Models\Table;
use BackedEnum;
use App\Services\GlobalFilterService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class TableResource extends Resource
{
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

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) $user && Gate::forUser($user)->allows('create', static::$model);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['store', 'currentOrder']);

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}
