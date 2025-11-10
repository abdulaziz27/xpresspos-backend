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
use Filament\Forms\Components\KeyValue;
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
                        Grid::make(2)
                            ->schema([
                                TextInput::make('metadata.payment_type')
                                    ->label('Payment Type')
                                    ->maxLength(255)
                                    ->helperText('Jenis pembayaran (e.g., credit_card, bank_transfer)'),

                                TextInput::make('metadata.bank')
                                    ->label('Bank')
                                    ->maxLength(255)
                                    ->helperText('Nama bank jika menggunakan transfer bank'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('metadata.card_type')
                                    ->label('Card Type')
                                    ->maxLength(255)
                                    ->helperText('Jenis kartu (e.g., visa, mastercard)'),

                                TextInput::make('metadata.saved_token_id')
                                    ->label('Saved Token ID')
                                    ->maxLength(255)
                                    ->helperText('ID token pembayaran yang tersimpan'),
                            ]),

                        Toggle::make('metadata.scheduled_downgrade')
                            ->label('Scheduled Downgrade')
                            ->default(false)
                            ->helperText('Apakah subscription dijadwalkan untuk downgrade'),

                        Textarea::make('metadata.notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Catatan tambahan tentang subscription'),

                        KeyValue::make('metadata.custom')
                            ->label('Custom Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Tambahkan metadata kustom lainnya jika diperlukan')
                            ->default([]),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
