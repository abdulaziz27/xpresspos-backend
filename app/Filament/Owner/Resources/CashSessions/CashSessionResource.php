<?php

namespace App\Filament\Owner\Resources\CashSessions;

use App\Filament\Owner\Resources\CashSessions\Pages\CreateCashSession;
use App\Filament\Owner\Resources\CashSessions\Pages\EditCashSession;
use App\Filament\Owner\Resources\CashSessions\Pages\ListCashSessions;
use App\Filament\Owner\Resources\CashSessions\Schemas\CashSessionForm;
use App\Filament\Owner\Resources\CashSessions\Tables\CashSessionsTable;
use App\Models\CashSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CashSessionResource extends Resource
{
    protected static ?string $model = CashSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cash Sessions';

    protected static ?string $modelLabel = 'Cash Session';

    protected static ?string $pluralModelLabel = 'Cash Sessions';

    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return CashSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashSessions::route('/'),
            'create' => CreateCashSession::route('/create'),
            'edit' => EditCashSession::route('/{record}/edit'),
        ];
    }
}
