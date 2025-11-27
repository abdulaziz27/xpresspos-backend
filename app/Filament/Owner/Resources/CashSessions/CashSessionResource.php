<?php

namespace App\Filament\Owner\Resources\CashSessions;

use App\Filament\Owner\Resources\CashSessions\Pages\CreateCashSession;
use App\Filament\Owner\Resources\CashSessions\Pages\EditCashSession;
use App\Filament\Owner\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\Owner\Resources\CashSessions\Schemas\CashSessionForm;
use App\Filament\Owner\Resources\CashSessions\RelationManagers\ExpensesRelationManager;
use App\Filament\Owner\Resources\CashSessions\Tables\CashSessionsTable;
use App\Models\CashSession;
use App\Enums\AssignmentRoleEnum;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
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
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;

        if (!$tenantId) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0');
        }

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['store', 'user'])
            ->whereHas('store', fn (Builder $storeQuery) => $storeQuery->where('tenant_id', $tenantId));

        // Check if user is owner (has owner role or owner assignment)
        $hasOwnerRole = $user->hasRole('owner');
        $hasOwnerAssignment = $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();
        $isOwner = $hasOwnerRole || $hasOwnerAssignment;

        if ($isOwner) {
            // Owner can see all stores in tenant
            return $query;
        } else {
            // Staff/User can only see stores they are assigned to
            $assignedStoreIds = $user->stores()->pluck('stores.id')->toArray();
            
            if (empty($assignedStoreIds)) {
                // No store assignment, return empty query
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('store', fn (Builder $storeQuery) => 
                $storeQuery->whereIn('stores.id', $assignedStoreIds)
            );
        }
    }
}
