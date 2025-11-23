<?php

namespace App\Filament\Owner\Resources\Recipes\Schemas;

use App\Models\Product;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use App\Support\Currency;

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
                                        return Product::withoutGlobalScopes()
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager),

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
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->step(0.01)
                                    ->helperText('Jumlah hasil yang dihasilkan dari resep ini (misal: 1 porsi, 2 cup, dll)'),

                                TextInput::make('yield_unit')
                                    ->label('Satuan Hasil')
                                    ->required()
                                    ->maxLength(50)
                                    ->default('porsi')
                                    ->placeholder('porsi, cup, pcs, dll')
                                    ->helperText('Satuan untuk jumlah hasil (misal: porsi, cup, pcs)'),
                            ]),

                        Toggle::make('is_active')
                            ->label('Resep Aktif')
                            ->default(true)
                            ->helperText('Hanya resep aktif yang dipakai untuk perhitungan biaya'),
                    ])
                    ->columns(1),

                Section::make('Ringkasan Biaya')
                    ->description('Perhitungan biaya resep (otomatis dari bahan)')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_cost')
                                    ->label('Total Cost (otomatis)')
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn($state, $record) => $record?->total_cost ? Currency::rupiah((float) $record->total_cost) : Currency::rupiah(0))
                                    ->helperText('Jumlah seluruh biaya bahan (dihitung otomatis dari recipe items)'),

                                TextInput::make('cost_per_unit')
                                    ->label('Cost per unit / HPP (otomatis)')
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(fn($state, $record) => $record?->cost_per_unit ? Currency::rupiah((float) $record->cost_per_unit) : Currency::rupiah(0))
                                    ->helperText('Total biaya ÷ jumlah hasil (dihitung otomatis). Ini yang akan jadi HPP produk.'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
