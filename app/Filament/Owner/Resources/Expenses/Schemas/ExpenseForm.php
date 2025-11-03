<?php

namespace App\Filament\Owner\Resources\Expenses\Schemas;

use App\Models\CashSession;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Support\Money;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengeluaran')
                    ->description('Detail pengeluaran dan kategorisasi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label('Deskripsi')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('mis: ATK, Utilitas'),

                                Select::make('category')
                                    ->label('Kategori')
                                    ->options([
                                        'office_supplies' => 'ATK',
                                        'utilities' => 'Utilitas',
                                        'rent' => 'Sewa',
                                        'marketing' => 'Marketing',
                                        'equipment' => 'Peralatan',
                                        'maintenance' => 'Perawatan',
                                        'travel' => 'Perjalanan',
                                        'food' => 'Makanan & Minuman',
                                        'other' => 'Lainnya',
                                    ])
                                    ->searchable()
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Jumlah')
                                    ->prefix('Rp')
                                    ->placeholder('50.000')
                                    ->helperText('Bisa input: 50000 atau 50.000')
                                    ->rule('required|numeric|min:0.01')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->required(),

                                DatePicker::make('expense_date')
                                    ->label('Tanggal Pengeluaran')
                                    ->default(now())
                                    ->required(),
                            ]),

                        TextInput::make('receipt_number')
                            ->label('Nomor Kwitansi')
                            ->maxLength(255)
                            ->placeholder('Nomor kwitansi atau invoice'),
                    ])
                    ->columns(1),

                Section::make('Vendor & Sesi Kas')
                    ->description('Informasi vendor dan asosiasi sesi kas')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('vendor')
                                    ->label('Vendor')
                                    ->maxLength(255)
                                    ->placeholder('Nama vendor / pemasok'),

                                Select::make('cash_session_id')
                                    ->label('Sesi Kas')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return CashSession::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('status', 'open')
                                            ->pluck('id', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Hubungkan ke sesi kas yang sedang dibuka'),
                            ]),

                        Select::make('user_id')
                            ->label('Dicatat Oleh')
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
                    ])
                    ->columns(1),

                Section::make('Catatan')
                    ->description('Catatan tambahan pengeluaran')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Catatan tambahan terkait pengeluaran ini'),
                    ])
                    ->columns(1),
            ]);
    }
}
