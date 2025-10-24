<?php

namespace App\Filament\Owner\Resources\MemberTiers\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberTierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tier Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Tier Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn($state, callable $set) => $set('slug', str()->slug($state))),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('min_points')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),

                                TextInput::make('max_points')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable(),

                                TextInput::make('discount_percentage')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label('Urutan Level')
                                    ->helperText('Angka kecil = level rendah, angka besar = level tinggi')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),

                        ColorPicker::make('color')
                            ->label('Tier Color')
                            ->default('#6B7280'),
                    ])
                    ->columns(1),

                Section::make('Benefits & Description')
                    ->schema([
                        Repeater::make('benefits')
                            ->label('Benefits')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Benefit Title')
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('details')
                                    ->label('Details')
                                    ->rows(2)
                                    ->maxLength(1000),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->addActionLabel('Add Benefit')
                            ->default([]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),
            ]);
    }
}
