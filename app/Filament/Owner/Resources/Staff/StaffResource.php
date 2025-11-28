<?php

namespace App\Filament\Owner\Resources\Staff;

use App\Filament\Owner\Resources\Staff\Pages\CreateStaff;
use App\Filament\Owner\Resources\Staff\Pages\EditStaff;
use App\Filament\Owner\Resources\Staff\Pages\ListStaff;
use App\Filament\Owner\Resources\Staff\RelationManagers\StoreUserAssignmentsRelationManager;
use App\Filament\Owner\Resources\Staff\RelationManagers\UserTenantAccessRelationManager;
use App\Filament\Owner\Resources\Staff\Schemas\StaffForm;
use App\Filament\Owner\Resources\Staff\Tables\StaffTable;
use App\Filament\Traits\HasPlanBasedNavigation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StaffResource extends Resource
{
    use HasPlanBasedNavigation;
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
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if (! Gate::forUser($user)->allows('create', static::$model)) {
            return false;
        }

        $tenantId = $user->currentTenant()?->id;
        if (! $tenantId) {
            return false;
        }

        $currentStaffCount = DB::table('user_tenant_access')
            ->where('tenant_id', $tenantId)
            ->count();

        $planCheck = static::canPerformPlanAction('create_staff', $currentStaffCount);

        return $planCheck['allowed'];
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // Prevent editing yourself (optional safety)
        if ($record->id === auth()->id()) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // Prevent deleting yourself
        if ($record->id === auth()->id()) {
            return false;
        }

        // Check permission first
        if (!Gate::forUser($user)->allows('delete', $record)) {
            return false;
        }

        // Prevent deleting the last owner in the tenant
        $currentTenant = $user->currentTenant();
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
        $query = parent::getEloquentQuery();

        // Filter users by current tenant via user_tenant_access
        $user = auth()->user();
        if ($user) {
            $tenantId = $user->currentTenant()?->id;
            if ($tenantId) {
                $query->whereHas('tenants', function ($q) use ($tenantId) {
                    $q->where('tenants.id', $tenantId);
                });
            } else {
                // If no tenant context, return empty result for safety
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}

