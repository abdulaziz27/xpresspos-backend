<?php

namespace App\Filament\Owner\Resources\LoyaltyPointTransactions;

use App\Filament\Owner\Resources\LoyaltyPointTransactions\Pages\ListLoyaltyPointTransactions;
use App\Filament\Owner\Resources\LoyaltyPointTransactions\Tables\LoyaltyPointTransactionsTable;
use App\Models\LoyaltyPointTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoyaltyPointTransactionResource extends Resource
{
    protected static ?string $model = LoyaltyPointTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Poin Loyalti';

    protected static ?string $modelLabel = 'Loyalty Point Transaction';

    protected static ?string $pluralModelLabel = 'Loyalty Point Transactions';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelanggan & Loyalti';

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