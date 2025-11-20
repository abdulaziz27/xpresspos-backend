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
use App\Support\Money;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pembayaran')
                    ->description('Detail dasar pembayaran dan informasi order')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('order_id')
                                    ->label('Order')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return Order::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('status', '!=', 'cancelled')
                                            ->pluck('order_number', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('payment_method')
                                    ->label('Metode Pembayaran')
                                    ->options([
                                        'cash' => 'Tunai',
                                        'credit_card' => 'Kartu Kredit',
                                        'debit_card' => 'Kartu Debit',
                                        'qris' => 'QRIS',
                                        'bank_transfer' => 'Transfer Bank',
                                        'e_wallet' => 'E-Wallet',
                                    ])
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Jumlah')
                                    ->prefix('Rp')
                                    ->placeholder('100.000')
                                    ->helperText('Bisa input: 100000 atau 100.000')
                                    ->rule('required|numeric|min:0.01')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->required(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Menunggu',
                                        'processing' => 'Diproses',
                                        'completed' => 'Berhasil',
                                        'failed' => 'Gagal',
                                        'cancelled' => 'Dibatalkan',
                                        'refunded' => 'Dikembalikan',
                                    ])
                                    ->default('pending')
                                    ->required(),
                            ]),

                        TextInput::make('reference_number')
                            ->label('Nomor Referensi')
                            ->maxLength(255)
                            ->placeholder('Nomor referensi transaksi'),
                    ])
                    ->columns(1),

                Section::make('Informasi Gateway')
                    ->description('Detail gateway pembayaran dan informasi pemrosesan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gateway')
                                    ->label('Gateway')
                                    ->maxLength(255)
                                    ->placeholder('Contoh: Xendit'),

                                TextInput::make('gateway_transaction_id')
                                    ->label('ID Transaksi Gateway')
                                    ->maxLength(255)
                                    ->placeholder('Referensi transaksi di gateway'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('gateway_fee')
                                    ->label('Biaya Gateway')
                                    ->prefix('Rp')
                                    ->placeholder('5.000')
                                    ->helperText('Bisa input: 5000 atau 5.000')
                                    ->rule('nullable|numeric|min:0')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->default(0),

                                DateTimePicker::make('processed_at')
                                    ->label('Diproses Pada')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Select::make('processed_by')
                            ->label('Diproses Oleh')
                            ->options(function () {
                                $storeId = auth()->user()?->currentStoreId();

                                return User::query()
                                    ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->gateway || $record?->status === 'completed'),

                Section::make('Informasi Tambahan')
                    ->description('Detail tambahan pembayaran dan catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Catatan tambahan untuk pembayaran ini'),

                        Textarea::make('gateway_response')
                            ->label('Respons Gateway')
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn($record) => $record?->gateway_response)
                            ->helperText('Respons mentah dari gateway pembayaran'),
                    ])
                    ->columns(1),
            ]);
    }
}
