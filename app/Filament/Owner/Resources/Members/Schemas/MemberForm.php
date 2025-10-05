<?php

namespace App\Filament\Owner\Resources\Members\Schemas;

use App\Models\MemberTier;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Member Information')
                    ->description('Basic member details and contact information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('member_number')
                                    ->label('Member Number')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_of_birth')
                                    ->label('Date of Birth')
                                    ->displayFormat('d/m/Y'),

                                Select::make('tier_id')
                                    ->label('Member Tier')
                                    ->options(function () {
                                        return MemberTier::where('store_id', auth()->user()->store_id)
                                            ->where('is_active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Textarea::make('address')
                            ->rows(3)
                            ->maxLength(500),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Loyalty Information')
                    ->description('Loyalty points and visit statistics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('loyalty_points')
                                    ->label('Loyalty Points')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_spent')
                                    ->label('Total Spent')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('visit_count')
                                    ->label('Visit Count')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                DatePicker::make('last_visit_at')
                                    ->label('Last Visit')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
