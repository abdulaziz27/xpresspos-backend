<?php

namespace App\Filament\Owner\Resources\Expenses\Schemas;

use App\Models\CashSession;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense Information')
                    ->description('Basic expense details and categorization')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Office supplies, Utilities'),

                                Select::make('category')
                                    ->label('Category')
                                    ->options([
                                        'office_supplies' => 'Office Supplies',
                                        'utilities' => 'Utilities',
                                        'rent' => 'Rent',
                                        'marketing' => 'Marketing',
                                        'equipment' => 'Equipment',
                                        'maintenance' => 'Maintenance',
                                        'travel' => 'Travel',
                                        'food' => 'Food & Beverage',
                                        'other' => 'Other',
                                    ])
                                    ->searchable()
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0.01)
                                    ->required(),

                                DatePicker::make('expense_date')
                                    ->label('Expense Date')
                                    ->default(now())
                                    ->required(),
                            ]),

                        TextInput::make('receipt_number')
                            ->label('Receipt Number')
                            ->maxLength(255)
                            ->placeholder('Receipt or invoice number'),
                    ])
                    ->columns(1),

                Section::make('Vendor & Cash Session')
                    ->description('Vendor information and cash session association')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('vendor')
                                    ->label('Vendor')
                                    ->maxLength(255)
                                    ->placeholder('Vendor or supplier name'),

                                Select::make('cash_session_id')
                                    ->label('Cash Session')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return CashSession::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('status', 'open')
                                            ->pluck('id', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Associate with open cash session'),
                            ]),

                        Select::make('user_id')
                            ->label('Recorded By')
                            ->options(function () {
                                $storeId = auth()->user()?->currentStoreId();

                                return User::query()
                                    ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->default(auth()->id())
                            ->required(),
                    ])
                    ->columns(1),

                Section::make('Notes')
                    ->description('Additional expense notes and details')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this expense'),
                    ])
                    ->columns(1),
            ]);
    }
}
