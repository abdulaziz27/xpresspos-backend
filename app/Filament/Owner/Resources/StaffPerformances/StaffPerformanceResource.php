<?php

namespace App\Filament\Owner\Resources\StaffPerformances;

use App\Filament\Owner\Resources\StaffPerformances\Pages\ListStaffPerformances;
use App\Filament\Owner\Resources\StaffPerformances\Tables\StaffPerformancesTable;
use App\Models\StaffPerformance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StaffPerformanceResource extends Resource
{
    protected static ?string $model = StaffPerformance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Staff Performance';

    protected static ?string $modelLabel = 'Staff Performance';

    protected static ?string $pluralModelLabel = 'Staff Performances';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return StaffPerformancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffPerformances::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Performance records are created automatically
    }

    public static function canEdit($record): bool
    {
        return false; // Performance records should not be editable
    }

    public static function canDelete($record): bool
    {
        return false; // Performance records should not be deletable
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes();

        if (!$tenantId) {
            return $query->whereRaw('1 = 0');
        }

        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        // StaffPerformance doesn't have tenant_id column, so filter via store relationship
        return $query->whereHas('store', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from navigation for MVP - too complex for initial release
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}