<?php

namespace App\Filament\Owner\Resources\Tables\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Table Information')
                    ->description('Basic table details and configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('table_number')
                                    ->label('Table Number')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Table 1, VIP Booth A'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('capacity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->default(4)
                                    ->suffix('people'),

                                Select::make('location')
                                    ->options([
                                        'indoor' => 'Indoor',
                                        'outdoor' => 'Outdoor',
                                        'terrace' => 'Terrace',
                                        'vip' => 'VIP Section',
                                        'bar' => 'Bar Area',
                                        'other' => 'Other',
                                    ])
                                    ->searchable()
                                    ->default('indoor'),
                            ]),

                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Special notes about this table'),
                    ])
                    ->columns(1),

                Section::make('Table Status')
                    ->description('Table availability and status settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->options([
                                        'available' => 'Available',
                                        'occupied' => 'Occupied',
                                        'reserved' => 'Reserved',
                                        'maintenance' => 'Maintenance',
                                        'cleaning' => 'Cleaning',
                                    ])
                                    ->default('available')
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Inactive tables will not appear in POS'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
