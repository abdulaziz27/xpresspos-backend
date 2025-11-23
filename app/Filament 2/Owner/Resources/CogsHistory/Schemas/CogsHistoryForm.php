<?php

namespace App\Filament\Owner\Resources\CogsHistory\Schemas;

use App\Filament\Owner\Resources\Concerns\ResolvesGlobalFilters;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\Money;

class CogsHistoryForm
{
    use ResolvesGlobalFilters;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi COGS')
                    ->description('Detail perhitungan Biaya Pokok Penjualan (COGS)')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(fn () => static::productOptions())
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

                                Select::make('order_id')
                                    ->label('Order (Opsional)')
                                    ->options(fn () => static::orderOptions())
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('quantity_sold')
                                    ->label('Jumlah Terjual')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $unitCost = $get('unit_cost');
                                        if ($state && $unitCost) {
                                            $set('total_cogs', $state * $unitCost);
                                        }
                                    }),

                                TextInput::make('unit_cost')
                                    ->label('Biaya per Unit')
                                    ->prefix('Rp')
                                    ->required()
                                    ->placeholder('8.000')
                                    ->helperText('Bisa input: 8000 atau 8.000')
                                    ->rule('required|numeric|min:0')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = $get('quantity_sold');
                                        if ($state && $quantity) {
                                            $set('total_cogs', $quantity * $state);
                                        }
                                    }),

                                TextInput::make('total_cogs')
                                    ->label('Total COGS')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Dihitung otomatis'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('calculation_method')
                                    ->label('Metode Perhitungan')
                                    ->options([
                                        'weighted_average' => 'Rata-rata Tertimbang',
                                        'fifo' => 'FIFO (First In, First Out)',
                                        'lifo' => 'LIFO (Last In, First Out)',
                                    ])
                                    ->default('weighted_average')
                                    ->required()
                                    ->helperText('Metode yang digunakan untuk menghitung biaya'),

                                TextInput::make('cost_breakdown')
                                    ->label('Rincian Biaya')
                                    ->helperText('Format JSON untuk rincian biaya'),
                            ]),
                    ])
                    ->columns(1),
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
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected static function orderOptions(): array
    {
        $tenantId = static::currentTenantId();

        if (! $tenantId) {
            return [];
        }

        $query = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed');

        $storeIds = static::currentStoreIds();
        if (! empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query
            ->latest('created_at')
            ->limit(200)
            ->pluck('order_number', 'id')
            ->toArray();
    }
}
