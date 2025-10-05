<?php

namespace App\Filament\Owner\Resources\CashSessions\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CashSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Session Information')
                    ->description('Basic cash session details and opening balance')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Cashier')
                                    ->options(function () {
                                        return User::where('store_id', auth()->user()->store_id)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'open' => 'Open',
                                        'closed' => 'Closed',
                                    ])
                                    ->default('open')
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('opening_balance')
                                    ->label('Opening Balance')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),

                                TextInput::make('closing_balance')
                                    ->label('Closing Balance')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),

                                TextInput::make('variance')
                                    ->label('Variance')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Session Summary')
                    ->description('Calculated session totals and statistics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('cash_sales')
                                    ->label('Cash Sales')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total cash sales during session'),

                                TextInput::make('cash_expenses')
                                    ->label('Cash Expenses')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total cash expenses during session'),

                                TextInput::make('expected_balance')
                                    ->label('Expected Balance')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Opening + Sales - Expenses'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->status === 'closed'),

                Section::make('Session Timing')
                    ->description('Session opening and closing times')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('opened_at')
                                    ->label('Opened At')
                                    ->default(now())
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),

                                DateTimePicker::make('closed_at')
                                    ->label('Closed At')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Notes')
                    ->description('Additional session notes and comments')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Session Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Notes about this cash session'),
                    ])
                    ->columns(1),
            ]);
    }
}
