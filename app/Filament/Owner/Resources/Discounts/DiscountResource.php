<?php

namespace App\Filament\Owner\Resources\Discounts;

use App\Filament\Owner\Resources\Discounts\Pages\CreateDiscount;
use App\Filament\Owner\Resources\Discounts\Pages\EditDiscount;
use App\Filament\Owner\Resources\Discounts\Pages\ListDiscounts;
use App\Filament\Owner\Resources\Discounts\Schemas\DiscountForm;
use App\Filament\Owner\Resources\Discounts\Tables\DiscountsTable;
use App\Models\Discount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Discounts';

    protected static ?string $modelLabel = 'Discount';

    protected static ?string $pluralModelLabel = 'Discounts';

    protected static ?int $navigationSort = 5;

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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = auth()->user();
        
        if ($user && $user->store_id) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->where('store_id', $user->store_id);
        }

        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->store_id) {
            setPermissionsTeamId($user->store_id);
        }

        return $user->hasRole('owner') || $user->hasAnyRole(['admin_sistem', 'manager']);
    }
}