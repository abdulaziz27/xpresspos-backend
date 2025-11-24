<?php

namespace App\Filament\Owner\Resources\Products;

use App\Filament\Owner\Resources\Products\Pages\CreateProduct;
use App\Filament\Owner\Resources\Products\Pages\EditProduct;
use App\Filament\Owner\Resources\Products\Pages\ListProducts;
use App\Filament\Owner\Resources\Products\RelationManagers;
use App\Filament\Owner\Resources\Products\Schemas\ProductForm;
use App\Filament\Owner\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Produk';

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?int $navigationSort = 11;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk';

    // Check if user can create more products based on subscription limit
    public static function canCreate(): bool
    {
        return auth()->user()->canCreate('products');
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
            RelationManagers\ModifierGroupsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
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
            ->withoutGlobalScopes()
            ->with(['category']);

        if (!$tenantId) {
            return $query->whereRaw('1 = 0');
        }

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        return $query->where('tenant_id', $tenantId);
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}
