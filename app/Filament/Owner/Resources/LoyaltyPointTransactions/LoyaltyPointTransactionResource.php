<?php

namespace App\Filament\Owner\Resources\LoyaltyPointTransactions;

use App\Filament\Owner\Resources\LoyaltyPointTransactions\Pages\ListLoyaltyPointTransactions;
use App\Filament\Owner\Resources\LoyaltyPointTransactions\Tables\LoyaltyPointTransactionsTable;
use App\Models\LoyaltyPointTransaction;
use App\Services\StoreContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyPointTransactionResource extends Resource
{
    protected static ?string $model = LoyaltyPointTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Poin Loyalti';

    protected static ?string $modelLabel = 'Loyalty Point Transaction';

    protected static ?string $pluralModelLabel = 'Loyalty Point Transactions';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Member & Loyalti';

    public static function table(Table $table): Table
    {
        return LoyaltyPointTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyPointTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Transactions are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // Transactions should not be editable
    }

    public static function canDelete($record): bool
    {
        return false; // Transactions should not be deletable
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $storeContext = StoreContext::instance();
        $storeId = $storeContext->current($user);

        $query = parent::getEloquentQuery();

        if ($storeId) {
            return $query->where('store_id', $storeId);
        }

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin_sistem')) {
            return $query;
        }

        $accessibleStores = $storeContext->accessibleStores($user)->pluck('id');

        if ($accessibleStores->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('store_id', $accessibleStores);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from navigation for MVP - too granular, available in Members section
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}