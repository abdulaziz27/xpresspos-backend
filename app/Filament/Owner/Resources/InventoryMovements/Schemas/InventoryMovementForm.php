<?php

namespace App\Filament\Owner\Resources\InventoryMovements\Schemas;

use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Support\Money;

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pergerakan Stok')
                    ->description('Detail pergerakan dan informasi produk')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return Product::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('track_inventory', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('type')
                                    ->label('Jenis Pergerakan')
                                    ->options([
                                        'sale' => 'Penjualan',
                                        'purchase' => 'Pembelian',
                                        'adjustment_in' => 'Penyesuaian Masuk',
                                        'adjustment_out' => 'Penyesuaian Keluar',
                                        'transfer_in' => 'Transfer Masuk',
                                        'transfer_out' => 'Transfer Keluar',
                                        'return' => 'Retur',
                                        'waste' => 'Waste/Rusak',
                                    ])
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->live()
                                    ->afterStateUpdated(function (callable $get, callable $set) {
                                        $quantity = $get('quantity');
                                        $unitCost = $get('unit_cost');
                                        if ($quantity && $unitCost) {
                                            $set('total_cost', $quantity * $unitCost);
                                        }
                                    }),

                                TextInput::make('unit_cost')
                                    ->label('Biaya per Unit')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->placeholder('8.000')
                                    ->helperText('Bisa input: 8000 atau 8.000')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->live()
                                    ->afterStateUpdated(function (callable $get, callable $set) {
                                        $quantity = $get('quantity');
                                        $unitCost = $get('unit_cost');
                                        if ($quantity && $unitCost) {
                                            $set('total_cost', $quantity * $unitCost);
                                        }
                                    }),
                            ]),

                        TextInput::make('total_cost')
                            ->label('Total Biaya')
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->minValue(0)
                            ->live()
                            ->formatStateUsing(function ($state, $record) {
                                if ($record?->total_cost) {
                                    return number_format($record->total_cost, 0, ',', '.');
                                }
                                if ($state) {
                                    return number_format($state, 0, ',', '.');
                                }
                                return '0';
                            })
                            ->disabled()
                            ->helperText('Dihitung otomatis dari jumlah × biaya per unit'),
                    ])
                    ->columns(1),

                Section::make('Referensi & Catatan')
                    ->description('Informasi referensi dan catatan tambahan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
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

                                TextInput::make('reason')
                                    ->label('Alasan')
                                    ->maxLength(255)
                                    ->placeholder('mis: Penyesuaian stok, Barang rusak'),
                            ]),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Catatan tambahan terkait pergerakan ini'),
                    ])
                    ->columns(1),

                Section::make('Waktu')
                    ->description('Informasi waktu pergerakan')
                    ->schema([
                        DateTimePicker::make('created_at')
                            ->label('Tanggal Pergerakan')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(1)
                    ->visible(fn($operation) => $operation === 'create'),
            ]);
    }
}
