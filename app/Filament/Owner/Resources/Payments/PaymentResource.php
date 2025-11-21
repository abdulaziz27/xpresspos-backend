<?php

namespace App\Filament\Owner\Resources\Payments;

use App\Filament\Owner\Resources\Payments\Pages\ListPayments;
use App\Filament\Owner\Resources\Payments\Pages\ViewPayment;
use App\Filament\Owner\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Owner\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use App\Services\StoreContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\Icons\Heroicon;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static ?int $navigationSort = 11;

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan & Laporan';



    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
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
