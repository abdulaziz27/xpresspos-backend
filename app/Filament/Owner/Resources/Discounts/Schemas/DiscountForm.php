<?php

namespace App\Filament\Owner\Resources\Discounts\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Discount Information')
                    ->description('Basic discount details and configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Weekend Special, Happy Hour'),

                                Select::make('type')
                                    ->required()
                                    ->options([
                                        'percentage' => 'Percentage (%)',
                                        'fixed' => 'Fixed Amount (Rp)',
                                    ])
                                    ->default('percentage')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('value', null);
                                    }),
                            ]),

                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Describe when and how this discount applies'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('value')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->prefix(fn (callable $get) => $get('type') === 'fixed' ? 'Rp' : null)
                                    ->suffix(fn (callable $get) => $get('type') === 'percentage' ? '%' : null)
                                    ->placeholder(fn (callable $get) => $get('type') === 'percentage' ? '10' : '50000')
                                    ->helperText(fn (callable $get) => $get('type') === 'percentage' 
                                        ? 'Enter percentage (e.g., 10 for 10%)' 
                                        : 'Enter fixed amount in Rupiah'),

                                DatePicker::make('expired_date')
                                    ->label('Expiry Date')
                                    ->placeholder('Leave empty for no expiry')
                                    ->minDate(now())
                                    ->helperText('Optional: Set when this discount expires'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Status & Activation')
                    ->description('Control when this discount is available')
                    ->schema([
                        Toggle::make('status')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active discounts can be applied to orders'),
                    ])
                    ->columns(1),
            ]);
    }
}