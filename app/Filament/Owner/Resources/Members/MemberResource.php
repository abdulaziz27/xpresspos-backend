<?php

namespace App\Filament\Owner\Resources\Members;

use App\Filament\Owner\Resources\Members\Pages\CreateMember;
use App\Filament\Owner\Resources\Members\Pages\EditMember;
use App\Filament\Owner\Resources\Members\Pages\ListMembers;
use App\Filament\Owner\Resources\Members\RelationManagers\LoyaltyTransactionsRelationManager;
use App\Filament\Owner\Resources\Members\Schemas\MemberForm;
use App\Filament\Owner\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Member';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Member';

    protected static ?int $navigationSort = 0;

    protected static string|\UnitEnum|null $navigationGroup = 'Member & Loyalti';




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
}
