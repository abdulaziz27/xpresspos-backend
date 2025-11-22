<?php

namespace App\Filament\Owner\Resources\InventoryMovements;

use App\Filament\Owner\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Owner\Resources\InventoryMovements\Pages\EditInventoryMovement;
use App\Filament\Owner\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Filament\Owner\Resources\InventoryMovements\Schemas\InventoryMovementForm;
use App\Filament\Owner\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use App\Models\InventoryMovement;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Pergerakan Stok';

    protected static ?string $modelLabel = 'Pergerakan Stok';

    protected static ?string $pluralModelLabel = 'Pergerakan Stok';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    /**
     * Hide from navigation - only for audit/advanced use.
     * Accessible via RelationManager from InventoryItemResource.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
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
            'index' => ListInventoryMovements::route('/'),
            'create' => CreateInventoryMovement::route('/create'),
            'edit' => EditInventoryMovement::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['inventoryItem', 'store', 'user']);

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}
