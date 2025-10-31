<?php

namespace App\Filament\Owner\Resources\Expenses;

use App\Filament\Owner\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Owner\Resources\Expenses\Pages\EditExpense;
use App\Filament\Owner\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Owner\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Owner\Resources\Expenses\Tables\ExpensesTable;
use App\Models\Expense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Pengeluaran';

    protected static ?string $modelLabel = 'Pengeluaran';

    protected static ?string $pluralModelLabel = 'Pengeluaran';

    protected static ?int $navigationSort = 0;

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan & Laporan';


    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
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
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
