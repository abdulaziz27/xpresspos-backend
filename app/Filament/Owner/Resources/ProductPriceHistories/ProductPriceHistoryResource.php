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

    protected static ?string $navigationLabel = 'Riwayat Harga';

    protected static ?string $modelLabel = 'Product Price History';

    protected static ?string $pluralModelLabel = 'Product Price Histories';

    protected static ?int $navigationSort = 40;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk';

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
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes();

        if (!$tenantId) {
            return $query->whereRaw('1 = 0');
        }

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        return $query->where('tenant_id', $tenantId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from navigation for MVP - audit feature, not daily operations
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}