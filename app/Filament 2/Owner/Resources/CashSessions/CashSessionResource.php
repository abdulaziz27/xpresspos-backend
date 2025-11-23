<?php

namespace App\Filament\Owner\Resources\CashSessions;

use App\Filament\Owner\Resources\CashSessions\Pages\CreateCashSession;
use App\Filament\Owner\Resources\CashSessions\Pages\EditCashSession;
use App\Filament\Owner\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\Owner\Resources\CashSessions\Schemas\CashSessionForm;
use App\Filament\Owner\Resources\CashSessions\RelationManagers\ExpensesRelationManager;
use App\Filament\Owner\Resources\CashSessions\Tables\CashSessionsTable;
use App\Models\CashSession;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashSessionResource extends Resource
{
    protected static ?string $model = CashSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Sesi Kas';

    protected static ?string $modelLabel = 'Sesi Kas';

    protected static ?string $pluralModelLabel = 'Sesi Kas';

    protected static ?int $navigationSort = 12;

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional Harian';


    public static function form(Schema $schema): Schema
    {
        return CashSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashSessions::route('/'),
            'create' => CreateCashSession::route('/create'),
            'edit' => EditCashSession::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canDelete($record): bool
    {
        // Protect cash session history - prevent deletion to maintain audit trail
        return false;
    }

    public static function canDeleteAny(): bool
    {
        // Protect cash session history - prevent bulk deletion
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['store', 'user']);

        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        if (! empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query;
    }
}
