<?php

namespace App\Filament\Owner\Resources\Recipes\Schemas;

use App\Models\Product;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Support\Money;

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Resep')
                    ->description('Detail resep dan keterkaitan produk')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(function () {
                                        // Product is tenant-scoped, TenantScope will automatically filter
                                        return Product::query()
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('name')
                                    ->label('Nama Resep')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('mis: Resep Espresso'),
                            ]),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Deskripsi resep dan catatan'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('yield_quantity')
                                    ->label('Jumlah Hasil')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $totalCost = $get('total_cost');
                                        if ($state && $totalCost && $state > 0) {
                                            $set('cost_per_unit', $totalCost / $state);
                                        }
                                    }),

                                Select::make('yield_unit')
                                    ->label('Satuan Hasil')
                                    ->options([
                                        'kg' => 'Kilogram',
                                        'g' => 'Gram',
                                        'l' => 'Liter',
                                        'ml' => 'Mililiter',
                                        'pcs' => 'Pcs',
                                        'cup' => 'Cangkir',
                                        'tbsp' => 'Sendok Makan',
                                        'tsp' => 'Sendok Teh',
                                    ])
                                    ->required()
                                    ->default('pcs'),
                            ]),

                        Toggle::make('is_active')
                            ->label('Resep Aktif')
                            ->default(true)
                            ->helperText('Hanya resep aktif yang dipakai untuk perhitungan biaya'),
                    ])
                    ->columns(1),

                Section::make('Bahan Resep')
                    ->description('Tambah bahan dan jumlahnya untuk resep ini')
                    ->schema([
                        Repeater::make('items')
                            ->label('Bahan')
                            ->relationship('items')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('ingredient_product_id')
                                            ->label('Bahan')
                                            ->options(function () {
                                                // Product is tenant-scoped, TenantScope will automatically filter
                                                return Product::query()
                                                    ->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state) {
                                                    $product = Product::find($state);
                                                    if ($product && $product->cost_price) {
                                                        $set('unit_cost', $product->cost_price);
                                                    }
                                                }
                                            }),

                                        TextInput::make('quantity')
                                            ->label('Jumlah')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0.01)
                                            ->step(0.01)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $unitCost = $get('unit_cost');
                                                if ($state && $unitCost) {
                                                    $set('total_cost', $state * $unitCost);
                                                }
                                            }),

                                        Select::make('unit')
                                            ->label('Satuan')
                                            ->options([
                                                'kg' => 'Kilogram',
                                                'g' => 'Gram',
                                                'l' => 'Liter',
                                                'ml' => 'Mililiter',
                                                'pcs' => 'Pcs',
                                                'cup' => 'Cangkir',
                                                'tbsp' => 'Sendok Makan',
                                                'tsp' => 'Sendok Teh',
                                            ])
                                            ->required()
                                            ->default('pcs'),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('unit_cost')
                                            ->label('Biaya per Satuan')
                                            ->prefix('Rp')
                                            ->required()
                                            ->placeholder('8.000')
                                            ->helperText('Bisa input: 8000 atau 8.000')
                                            ->rule('required|numeric|min:0')
                                            ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $quantity = $get('quantity');
                                                if ($state && $quantity) {
                                                    $set('total_cost', $quantity * $state);
                                                }
                                            }),

                                        TextInput::make('total_cost')
                                            ->label('Total Biaya')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->step(0.01)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Dihitung otomatis'),
                                    ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('Tambah Bahan')
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                ($state['ingredient_product_id'] ?? null) ? Product::find($state['ingredient_product_id'])?->name : null
                            )
                            ->reorderable()
                            ->deleteAction(
                                fn($action) => $action->requiresConfirmation()
                            ),
                    ])
                    ->columns(1),

                Section::make('Ringkasan Biaya')
                    ->description('Perhitungan biaya resep')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_cost')
                                    ->label('Total Biaya Resep')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->formatStateUsing(fn($state, $record) => $record?->total_cost ? number_format($record->total_cost, 0, ',', '.') : ($state ? number_format($state, 0, ',', '.') : '0'))
                                    ->helperText('Jumlah seluruh biaya bahan'),

                                TextInput::make('cost_per_unit')
                                    ->label('Biaya per Unit')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->formatStateUsing(fn($state, $record) => $record?->cost_per_unit ? number_format($record->cost_per_unit, 0, ',', '.') : ($state ? number_format($state, 0, ',', '.') : '0'))
                                    ->helperText('Total biaya ÷ jumlah hasil'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
