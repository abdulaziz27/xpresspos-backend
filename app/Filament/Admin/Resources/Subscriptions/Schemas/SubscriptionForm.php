<?php

namespace App\Filament\Admin\Resources\Subscriptions\Schemas;

use App\Models\Plan;
use App\Models\Store;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Support\Money;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription Information')
                    ->description('Basic subscription details and store information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('store_id')
                                    ->label('Store')
                                    ->options(function () {
                                        return Store::where('status', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('plan_id')
                                    ->label('Plan')
                                    ->options(function () {
                                        return Plan::pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                        'cancelled' => 'Cancelled',
                                        'expired' => 'Expired',
                                        'trial' => 'Trial',
                                    ])
                                    ->default('active')
                                    ->required(),

                                Select::make('billing_cycle')
                                    ->label('Billing Cycle')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'yearly' => 'Yearly',
                                    ])
                                    ->default('monthly')
                                    ->required(),
                            ]),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->prefix('Rp')
                            ->placeholder('100.000')
                            ->helperText('Bisa input: 100000 atau 100.000')
                            ->rule('required|numeric|min:0')
                            ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                            ->required(),
                    ])
                    ->columns(1),

                Section::make('Subscription Dates')
                    ->description('Subscription start, end, and trial dates')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('starts_at')
                                    ->label('Starts At')
                                    ->default(now())
                                    ->required(),

                                DatePicker::make('ends_at')
                                    ->label('Ends At')
                                    ->required()
                                    ->helperText('Subscription expiration date'),

                                DatePicker::make('trial_ends_at')
                                    ->label('Trial Ends At')
                                    ->helperText('Trial period end date (optional)'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Metadata')
                    ->description('Additional subscription metadata and settings')
                    ->schema([
                        Textarea::make('metadata')
                            ->label('Metadata (JSON)')
                            ->rows(5)
                            ->helperText('Additional subscription data in JSON format')
                            ->default('{}')
                            ->placeholder('{"features": [], "limits": {}}'),
                    ])
                    ->columns(1),
            ]);
    }
}
