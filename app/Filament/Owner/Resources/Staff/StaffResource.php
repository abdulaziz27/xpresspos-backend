<?php

namespace App\Filament\Owner\Resources\Staff;

use App\Filament\Owner\Resources\Staff\Pages\CreateStaff;
use App\Filament\Owner\Resources\Staff\Pages\EditStaff;
use App\Filament\Owner\Resources\Staff\Pages\ListStaff;
use App\Filament\Owner\Resources\Staff\RelationManagers\StoreUserAssignmentsRelationManager;
use App\Filament\Owner\Resources\Staff\RelationManagers\UserTenantAccessRelationManager;
use App\Filament\Owner\Resources\Staff\Schemas\StaffForm;
use App\Filament\Owner\Resources\Staff\Tables\StaffTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff';

    protected static ?string $pluralModelLabel = 'Staff';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Toko & Tim';

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UserTenantAccessRelationManager::class,
            StoreUserAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        // Prevent editing yourself (optional safety)
        return $record->id !== auth()->id();
    }

    public static function canDelete(Model $record): bool
    {
        // Prevent deleting yourself
        if ($record->id === auth()->id()) {
            return false;
        }

        // Prevent deleting the last owner in the tenant
        $currentTenant = auth()->user()?->currentTenant();
        if ($currentTenant) {
            $ownerCount = DB::table('user_tenant_access')
                ->where('tenant_id', $currentTenant->id)
                ->where('role', 'owner')
                ->count();

            $isOwner = DB::table('user_tenant_access')
                ->where('user_id', $record->id)
                ->where('tenant_id', $currentTenant->id)
                ->where('role', 'owner')
                ->exists();

            // Don't allow deleting if this is the last owner
            if ($isOwner && $ownerCount <= 1) {
                return false;
            }
        }

        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes();

        // Filter users by current tenant via user_tenant_access
        // This ensures page independence from dashboard store filter
        $user = auth()->user();
        if ($user) {
            $tenantId = $user->currentTenant()?->id;
            if ($tenantId) {
                $query->whereHas('tenants', function ($q) use ($tenantId) {
                    $q->where('tenants.id', $tenantId);
                });
            }
        }

        return $query;
    }
}

