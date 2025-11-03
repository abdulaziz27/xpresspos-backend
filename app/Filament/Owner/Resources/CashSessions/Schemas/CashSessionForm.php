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
use App\Support\Money;

class CashSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Sesi')
                    ->description('Detail sesi kas dan saldo awal')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Kasir')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return User::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'open' => 'Dibuka',
                                        'closed' => 'Ditutup',
                                    ])
                                    ->default('open')
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('opening_balance')
                                    ->label('Saldo Awal')
                                    ->prefix('Rp')
                                    ->placeholder('100.000')
                                    ->helperText('Bisa input: 100000 atau 100.000')
                                    ->rule('required|numeric|min:0')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),

                                TextInput::make('closing_balance')
                                    ->label('Saldo Akhir')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),

                                TextInput::make('variance')
                                    ->label('Selisih')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Ringkasan Sesi')
                    ->description('Total dan statistik sesi yang dihitung')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('cash_sales')
                                    ->label('Penjualan Tunai')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total penjualan tunai selama sesi'),

                                TextInput::make('cash_expenses')
                                    ->label('Pengeluaran Tunai')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total pengeluaran tunai selama sesi'),

                                TextInput::make('expected_balance')
                                    ->label('Saldo Perkiraan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Saldo awal + Penjualan - Pengeluaran'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->status === 'closed'),

                Section::make('Waktu Sesi')
                    ->description('Waktu pembukaan dan penutupan sesi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('opened_at')
                                    ->label('Dibuka Pada')
                                    ->default(now())
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),

                                DateTimePicker::make('closed_at')
                                    ->label('Ditutup Pada')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Catatan')
                    ->description('Catatan tambahan untuk sesi ini')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan Sesi')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Catatan terkait sesi kas ini'),
                    ])
                    ->columns(1),
            ]);
    }
}
