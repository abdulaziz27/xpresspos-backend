<?php

namespace App\Filament\Owner\Resources\Discounts;

use App\Filament\Owner\Resources\Discounts\Pages\CreateDiscount;
use App\Filament\Owner\Resources\Discounts\Pages\EditDiscount;
use App\Filament\Owner\Resources\Discounts\Pages\ListDiscounts;
use App\Filament\Owner\Resources\Discounts\Schemas\DiscountForm;
use App\Filament\Owner\Resources\Discounts\Tables\DiscountsTable;
use App\Models\Discount;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Diskon';

    protected static ?string $modelLabel = 'Diskon';

    protected static ?string $pluralModelLabel = 'Diskon';

    protected static ?int $navigationSort = 30;

    protected static string|\UnitEnum|null $navigationGroup = 'Promo & Kampanye';

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return DiscountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscounts::route('/'),
            'create' => CreateDiscount::route('/create'),
            'edit' => EditDiscount::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('store');

        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        if (! empty($storeIds)) {
            // Include global discounts (store_id is null) and store-specific discounts
            $query->where(function (Builder $query) use ($storeIds) {
                $query
                    ->whereNull('store_id') // Global discounts
                    ->orWhereIn('store_id', $storeIds); // Store-specific discounts
            });
        }

        return $query;
    }

    public static function storeOptions(): array
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getAvailableStores(auth()->user())
            ->pluck('name', 'id')
            ->toArray();
    }
}