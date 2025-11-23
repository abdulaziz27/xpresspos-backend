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
        // Hide from navigation for MVP - too complex for initial release
        return false;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
}