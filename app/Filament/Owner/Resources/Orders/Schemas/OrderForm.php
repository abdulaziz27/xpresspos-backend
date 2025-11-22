<?php

namespace App\Filament\Owner\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pesanan')
                    ->description('Edit informasi ringan tanpa mengubah transaksi utama.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('No. Pesanan')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('customer_name')
                                    ->label('Nama Pelanggan')
                                    ->placeholder('Pelanggan Umum')
                                            ->maxLength(255),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'open' => 'Terbuka',
                                'completed' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->required(),

                                Select::make('payment_mode')
                                    ->label('Mode Pembayaran')
                                    ->options([
                                        'direct' => 'Langsung Dibayar',
                                        'open_bill' => 'Open Bill',
                                    ])
                                    ->native(false)
                                    ->placeholder('Tidak Diketahui'),
                            ]),

                        Textarea::make('notes')
                            ->label('Catatan Pesanan')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Waktu')
                    ->description('Timestamp dibuat otomatis oleh sistem POS.')
                    ->schema([
                        DateTimePicker::make('completed_at')
                            ->label('Selesai Pada')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => filled($record?->completed_at)),
            ]);
    }
}
