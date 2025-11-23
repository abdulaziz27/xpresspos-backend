<?php

namespace App\Filament\Owner\Resources\StoreUserAssignments;

use App\Filament\Owner\Resources\StoreUserAssignments\Pages\CreateStoreUserAssignment;
use App\Filament\Owner\Resources\StoreUserAssignments\Pages\EditStoreUserAssignment;
use App\Filament\Owner\Resources\StoreUserAssignments\Pages\ListStoreUserAssignments;
use App\Filament\Owner\Resources\StoreUserAssignments\Pages\ViewStoreUserAssignment;
use App\Filament\Owner\Resources\StoreUserAssignments\Schemas\StoreUserAssignmentForm;
use App\Filament\Owner\Resources\StoreUserAssignments\Schemas\StoreUserAssignmentInfolist;
use App\Filament\Owner\Resources\StoreUserAssignments\Tables\StoreUserAssignmentsTable;
use App\Models\StoreUserAssignment;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoreUserAssignmentResource extends Resource
{
    protected static ?string $model = StoreUserAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Karyawan';

    protected static ?string $pluralModelLabel = 'Karyawan';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = null;

    /**
     * Hide from navigation - functionality is available via:
     * - StoreResource (RelationManager: Staff di Toko)
     * - StaffResource (RelationManager: Tugas Toko)
     * - RoleResource (Role & Hak Akses)
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return StoreUserAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StoreUserAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreUserAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['user', 'store'])
            ->select([
                'id', 'store_id', 'user_id', 'assignment_role', 
                'is_primary', 'created_at', 'updated_at',
            ]);

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        // StoreUserAssignment doesn't have tenant_id column, so filter via store relationship
        if ($tenantId) {
            $query->whereHas('store', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create', static::getModel());
    }

    public static function canView($record): bool
    {
        return auth()->user()->can('view', $record);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update', $record);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreUserAssignments::route('/'),
            'create' => CreateStoreUserAssignment::route('/create'),
            'view' => ViewStoreUserAssignment::route('/{record}'),
            'edit' => EditStoreUserAssignment::route('/{record}/edit'),
        ];
    }
}
