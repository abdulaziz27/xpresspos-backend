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

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movement Information')
                    ->description('Basic movement details and product information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(function () {
                                        return Product::where('store_id', auth()->user()->store_id)
                                            ->where('track_inventory', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('type')
                                    ->label('Movement Type')
                                    ->options([
                                        'sale' => 'Sale',
                                        'purchase' => 'Purchase',
                                        'adjustment_in' => 'Adjustment In',
                                        'adjustment_out' => 'Adjustment Out',
                                        'transfer_in' => 'Transfer In',
                                        'transfer_out' => 'Transfer Out',
                                        'return' => 'Return',
                                        'waste' => 'Waste',
                                    ])
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('quantity')
                                    ->label('Quantity')
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
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
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
                            ->label('Total Cost')
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Automatically calculated from quantity × unit cost'),
                    ])
                    ->columns(1),

                Section::make('Reference & Notes')
                    ->description('Reference information and additional notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Recorded By')
                                    ->options(function () {
                                        return User::where('store_id', auth()->user()->store_id)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required(),

                                TextInput::make('reason')
                                    ->label('Reason')
                                    ->maxLength(255)
                                    ->placeholder('e.g., Stock adjustment, Damaged goods'),
                            ]),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Additional notes about this movement'),
                    ])
                    ->columns(1),

                Section::make('Timestamps')
                    ->description('Movement timing information')
                    ->schema([
                        DateTimePicker::make('created_at')
                            ->label('Movement Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(1)
                    ->visible(fn($operation) => $operation === 'create'),
            ]);
    }
}
