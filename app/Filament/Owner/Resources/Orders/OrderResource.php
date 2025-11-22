<?php

namespace App\Filament\Owner\Resources\Orders;

use App\Filament\Owner\Resources\Orders\Pages\ListOrders;
use App\Filament\Owner\Resources\Orders\Pages\ViewOrder;
use App\Filament\Owner\Resources\Orders\Schemas\OrderForm;
use App\Filament\Owner\Resources\Orders\Tables\OrdersTable;
use App\Filament\Owner\Resources\Orders\RelationManagers\OrderDiscountsRelationManager;
use App\Filament\Owner\Resources\Orders\RelationManagers\OrderItemsRelationManager;
use App\Filament\Owner\Resources\Orders\RelationManagers\PaymentsRelationManager;
use App\Filament\Owner\Resources\Orders\RelationManagers\RefundsRelationManager;
use App\Models\Order;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Icons\Heroicon;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $modelLabel = 'Pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional Harian';



    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
            OrderDiscountsRelationManager::class,
            PaymentsRelationManager::class,
            RefundsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    /**
     * Apply tenant filter only - store filtering is handled by table filters
     * 
     * This ensures page independence from dashboard filters.
     * Users can filter by store using the table filter instead.
     */
    public static function getEloquentQuery(): Builder
    {
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        $query = parent::getEloquentQuery()->withoutGlobalScopes();

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}
