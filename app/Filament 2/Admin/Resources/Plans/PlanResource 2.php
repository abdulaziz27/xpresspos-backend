<?php

namespace App\Filament\Admin\Resources\Plans;

use App\Filament\Admin\Resources\Plans\Pages\CreatePlan;
use App\Filament\Admin\Resources\Plans\Pages\EditPlan;
use App\Filament\Admin\Resources\Plans\Pages\ListPlans;
use App\Filament\Admin\Resources\Plans\Pages\ViewPlan;
use App\Filament\Admin\Resources\Plans\RelationManagers\PlanFeaturesRelationManager;
use App\Filament\Admin\Resources\Plans\Schemas\PlanForm;
use App\Filament\Admin\Resources\Plans\Tables\PlansTable;
use App\Models\Plan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Plans';

    protected static ?string $modelLabel = 'Plan';

    protected static ?string $pluralModelLabel = 'Plans';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Plans & Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PlanFeaturesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'view' => ViewPlan::route('/{record}'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}

