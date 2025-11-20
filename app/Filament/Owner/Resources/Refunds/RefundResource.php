<?php

namespace App\Filament\Owner\Resources\Refunds;

use App\Filament\Owner\Resources\Refunds\Pages\CreateRefund;
use App\Filament\Owner\Resources\Refunds\Pages\EditRefund;
use App\Filament\Owner\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Owner\Resources\Refunds\Schemas\RefundForm;
use App\Filament\Owner\Resources\Refunds\Tables\RefundsTable;
use App\Models\Refund;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?string $navigationLabel = 'Refund';

    protected static ?string $modelLabel = 'Refund';

    protected static ?string $pluralModelLabel = 'Refund';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Promo & Kampanye';

    public static function form(Schema $schema): Schema
    {
        return RefundForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RefundsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
            'create' => CreateRefund::route('/create'),
            'edit' => EditRefund::route('/{record}/edit'),
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
        return true;
    }
}