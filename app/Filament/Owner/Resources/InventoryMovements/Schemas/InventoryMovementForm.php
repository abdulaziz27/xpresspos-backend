<?php

namespace App\Filament\Owner\Resources\InventoryMovements\Schemas;

use App\Filament\Owner\Resources\Concerns\ResolvesGlobalFilters;
use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\Money;

class InventoryMovementForm
{
    use ResolvesGlobalFilters;

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
                                    ->options(fn () => static::productOptions())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('type')
                                    ->label('Jenis Pergerakan')
                                    ->options([
                                        // Hanya tipe manual yang diizinkan dibuat dari UI:
                                        // Pembelian, Penyesuaian, dan Transfer.
                                        'purchase' => 'Pembelian',
                                        'adjustment_in' => 'Penyesuaian Masuk',
                                        'adjustment_out' => 'Penyesuaian Keluar',
                                        'transfer_in' => 'Transfer Masuk',
                                        'transfer_out' => 'Transfer Keluar',
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
                                    ->prefix('Rp')
                                    ->placeholder('8.000')
                                    ->helperText('Bisa input: 8000 atau 8.000')
                                    ->rule('nullable|numeric|min:0')
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
                                    ->options(fn () => static::userOptionsForCurrentContext())
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
                            ->placeholder('Catatan tambahan terkait pergerakan ini. Catatan: Penjualan/Produksi/Retur/Waste dibuat otomatis dari proses operasional.'),
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

    /**
     * @return array<int, string>
     */
    protected static function productOptions(): array
    {
        $tenantId = static::currentTenantId();

        if (! $tenantId) {
            return [];
        }

        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
