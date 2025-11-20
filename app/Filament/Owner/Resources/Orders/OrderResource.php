<?php

namespace App\Filament\Owner\Resources\Orders;

use App\Filament\Owner\Resources\Orders\Pages\ListOrders;
use App\Filament\Owner\Resources\Orders\Pages\ViewOrder;
use App\Filament\Owner\Resources\Orders\Schemas\OrderForm;
use App\Filament\Owner\Resources\Orders\Tables\OrdersTable;
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
            //
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
     * Apply Global Filter to Orders query
     * 
     * Unified Multi-Store Dashboard: Filter by tenant + store + date
     */
    public static function getEloquentQuery(): Builder
    {
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        $query = parent::getEloquentQuery();

        // Apply store filter (multi-store support)
        if (!empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query;
    }
}
