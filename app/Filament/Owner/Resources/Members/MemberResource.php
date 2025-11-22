<?php

namespace App\Filament\Owner\Resources\Members;

use App\Filament\Owner\Resources\Members\Pages\CreateMember;
use App\Filament\Owner\Resources\Members\Pages\EditMember;
use App\Filament\Owner\Resources\Members\Pages\ListMembers;
use App\Filament\Owner\Resources\Members\RelationManagers\LoyaltyTransactionsRelationManager;
use App\Filament\Owner\Resources\Members\RelationManagers\OrdersRelationManager;
use App\Filament\Owner\Resources\Members\Schemas\MemberForm;
use App\Filament\Owner\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Member';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Member';

    protected static ?int $navigationSort = 0;

    protected static string|\UnitEnum|null $navigationGroup = 'Member & Loyalty';




    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            LoyaltyTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        // Start with base query (TenantScope will be applied automatically)
        // TenantScope ensures we only see members from current tenant
        $query = parent::getEloquentQuery();

        // Debug logging
        $user = auth()->user();
        $tenantId = $user?->currentTenant()?->id;
        $count = $query->count();
        
        \Log::info('MemberResource::getEloquentQuery', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'tenant_id' => $tenantId,
            'query_count' => $count,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        return $query;
    }
}
