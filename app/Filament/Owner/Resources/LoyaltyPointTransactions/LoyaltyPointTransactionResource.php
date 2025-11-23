<?php

namespace App\Filament\Owner\Resources\LoyaltyPointTransactions;

use App\Filament\Owner\Resources\LoyaltyPointTransactions\Pages\ListLoyaltyPointTransactions;
use App\Filament\Owner\Resources\LoyaltyPointTransactions\Tables\LoyaltyPointTransactionsTable;
use App\Models\LoyaltyPointTransaction;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyPointTransactionResource extends Resource
{
    protected static ?string $model = LoyaltyPointTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    // Navigation properties - all set to null/hidden since this resource is relation-only
    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Loyalty Point Transaction';

    protected static ?string $pluralModelLabel = 'Loyalty Point Transactions';

    protected static ?int $navigationSort = null;

    protected static string|\UnitEnum|null $navigationGroup = null;

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
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['member', 'order', 'user', 'store']);

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
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