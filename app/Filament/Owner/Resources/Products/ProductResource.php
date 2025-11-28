<?php

namespace App\Filament\Owner\Resources\Products;

use App\Filament\Owner\Resources\Products\Pages\CreateProduct;
use App\Filament\Owner\Resources\Products\Pages\EditProduct;
use App\Filament\Owner\Resources\Products\Pages\ListProducts;
use App\Filament\Owner\Resources\Products\RelationManagers;
use App\Filament\Owner\Resources\Products\Schemas\ProductForm;
use App\Filament\Owner\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Filament\Traits\HasPlanBasedNavigation;

class ProductResource extends Resource
{
    use HasPlanBasedNavigation;

    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Produk';

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    protected static ?int $navigationSort = 11;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    // Check if user can create more products based on subscription limit
    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Check plan limit first
        $check = static::canPerformPlanAction('create_product', static::$model::count());
        if (!$check['allowed']) {
            return false;
        }

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
        return Gate::forUser($user)->allows('delete', $record);
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

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0');
        }

        $tenantId = $user->currentTenant()?->id;

        if (! $tenantId) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['category'])
            ->where('tenant_id', $tenantId);
    }

}
