<?php

namespace App\Filament\Owner\Resources\MemberTiers;

use App\Filament\Owner\Resources\MemberTiers\Pages\CreateMemberTier;
use App\Filament\Owner\Resources\MemberTiers\Pages\EditMemberTier;
use App\Filament\Owner\Resources\MemberTiers\Pages\ListMemberTiers;
use App\Filament\Owner\Resources\MemberTiers\Schemas\MemberTierForm;
use App\Filament\Owner\Resources\MemberTiers\Tables\MemberTierTable;
use App\Models\MemberTier;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MemberTierResource extends Resource
{
    protected static ?string $model = MemberTier::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationLabel = 'Member Tiers';

    protected static ?string $modelLabel = 'Member Tier';

    protected static ?string $pluralModelLabel = 'Member Tiers';

    protected static ?int $navigationSort = 0;

    protected static string|UnitEnum|null $navigationGroup = 'Customer Management';

    public static function form(Schema $schema): Schema
    {
        return MemberTierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberTierTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberTiers::route('/'),
            'create' => CreateMemberTier::route('/create'),
            'edit' => EditMemberTier::route('/{record}/edit'),
        ];
    }
}
