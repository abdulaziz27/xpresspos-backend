<?php

namespace App\Filament\Owner\Resources\Payments\Schemas;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Information')
                    ->description('Basic payment details and order information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('order_id')
                                    ->label('Order')
                                    ->options(function () {
                                        return Order::where('store_id', auth()->user()->store_id)
                                            ->where('status', '!=', 'cancelled')
                                            ->pluck('order_number', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash' => 'Cash',
                                        'card' => 'Card',
                                        'qris' => 'QRIS',
                                        'transfer' => 'Bank Transfer',
                                        'other' => 'Other',
                                    ])
                                    ->required()
                                    ->live(),
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

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'cancelled' => 'Cancelled',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->default('pending')
                                    ->required(),
                            ]),

                        TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->maxLength(255)
                            ->placeholder('Transaction reference number'),
                    ])
                    ->columns(1),

                Section::make('Gateway Information')
                    ->description('Payment gateway details and processing information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gateway')
                                    ->label('Gateway')
                                    ->maxLength(255)
                                    ->placeholder('e.g., Midtrans, Xendit'),

                                TextInput::make('gateway_transaction_id')
                                    ->label('Gateway Transaction ID')
                                    ->maxLength(255)
                                    ->placeholder('Gateway transaction reference'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('gateway_fee')
                                    ->label('Gateway Fee')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->default(0),

                                DateTimePicker::make('processed_at')
                                    ->label('Processed At')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Select::make('processed_by')
                            ->label('Processed By')
                            ->options(function () {
                                return User::where('store_id', auth()->user()->store_id)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->gateway || $record?->status === 'completed'),

                Section::make('Additional Information')
                    ->description('Additional payment details and notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this payment'),

                        Textarea::make('gateway_response')
                            ->label('Gateway Response')
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn($record) => $record?->gateway_response)
                            ->helperText('Raw response from payment gateway'),
                    ])
                    ->columns(1),
            ]);
    }
}
