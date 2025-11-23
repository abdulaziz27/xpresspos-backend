<?php

namespace App\Filament\Owner\Resources\Roles;

use App\Filament\Owner\Resources\Roles\Pages\CreateRole;
use App\Filament\Owner\Resources\Roles\Pages\EditRole;
use App\Filament\Owner\Resources\Roles\Pages\ListRoles;
use App\Filament\Owner\Resources\Roles\Schemas\RoleForm;
use App\Filament\Owner\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Role & Hak Akses';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Role';

    protected static ?int $navigationSort = 30;

    protected static string|\UnitEnum|null $navigationGroup = 'Toko & Tim';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        // Owner tidak bisa membuat role baru, hanya bisa edit permissions role yang sudah ada
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Don't allow editing system roles
        return !$record->isSystemRole();
    }

    public static function canDelete(Model $record): bool
    {
        // Owner tidak bisa menghapus role
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Filter by current tenant
        $user = auth()->user();
        if ($user) {
            $tenantId = $user->currentTenant()?->id;
            if ($tenantId) {
                // Hanya tampilkan role untuk tenant ini
                $query->where('tenant_id', $tenantId);
            }
        }

        // Hanya tampilkan role yang sudah disediakan: cashier, manager, owner
        $query->whereIn('name', ['cashier', 'manager', 'owner']);

        return $query;
    }

    /**
     * Ensure default roles exist for current tenant.
     */
    public static function ensureDefaultRoles(): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        $tenantId = $user->currentTenant()?->id;
        if (!$tenantId) {
            return;
        }

        $defaultRoles = ['cashier', 'manager', 'owner'];

        foreach ($defaultRoles as $roleName) {
            Role::firstOrCreate(
                [
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenantId,
                ],
                [
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenantId,
                ]
            );
        }
    }
}

