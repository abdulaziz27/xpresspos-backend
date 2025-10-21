<?php

namespace App\Filament\Owner\Resources\CogsHistory\Schemas;

use App\Models\Order;
use App\Models\Product;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CogsHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('COGS Information')
                    ->description('Cost of Goods Sold calculation details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return Product::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
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

                                Select::make('order_id')
                                    ->label('Order (Optional)')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return Order::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('status', 'completed')
                                            ->pluck('order_number', 'id');
                                    })
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('quantity_sold')
                                    ->label('Quantity Sold')
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
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->required()
                                    ->minValue(0)
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
                                    ->helperText('Calculated automatically'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('calculation_method')
                                    ->label('Calculation Method')
                                    ->options([
                                        'weighted_average' => 'Weighted Average',
                                        'fifo' => 'FIFO (First In, First Out)',
                                        'lifo' => 'LIFO (Last In, First Out)',
                                    ])
                                    ->default('weighted_average')
                                    ->required()
                                    ->helperText('Method used to calculate the cost'),

                                TextInput::make('cost_breakdown')
                                    ->label('Cost Breakdown')
                                    ->helperText('JSON format for detailed cost breakdown')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
