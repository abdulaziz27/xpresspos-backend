<?php

namespace App\Filament\Owner\Resources\Refunds;

use App\Filament\Owner\Resources\Refunds\Pages\CreateRefund;
use App\Filament\Owner\Resources\Refunds\Pages\EditRefund;
use App\Filament\Owner\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Owner\Resources\Refunds\Schemas\RefundForm;
use App\Filament\Owner\Resources\Refunds\Tables\RefundsTable;
use App\Models\Refund;
use App\Services\StoreContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?string $navigationLabel = 'Refund';

    protected static ?string $modelLabel = 'Refund';

    protected static ?string $pluralModelLabel = 'Refund';

    protected static ?int $navigationSort = 12;

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $storeContext = StoreContext::instance();
        $storeId = $storeContext->current($user);

        $query = parent::getEloquentQuery()->withoutGlobalScopes();

        if ($storeId) {
            return $query->where('store_id', $storeId);
        }

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin_sistem')) {
            return $query;
        }

        $storeIds = $storeContext->accessibleStores($user)->pluck('id');

        if ($storeIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('store_id', $storeIds);
    }
}