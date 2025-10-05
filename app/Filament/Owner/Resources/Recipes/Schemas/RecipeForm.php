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

class RecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recipe Information')
                    ->description('Basic recipe details and product association')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(function () {
                                        return Product::where('store_id', auth()->user()->store_id)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('name')
                                    ->label('Recipe Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Espresso Recipe'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Recipe description and notes'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('yield_quantity')
                                    ->label('Yield Quantity')
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
                                    ->label('Yield Unit')
                                    ->options([
                                        'kg' => 'Kilogram',
                                        'g' => 'Gram',
                                        'l' => 'Liter',
                                        'ml' => 'Milliliter',
                                        'pcs' => 'Pieces',
                                        'cup' => 'Cup',
                                        'tbsp' => 'Tablespoon',
                                        'tsp' => 'Teaspoon',
                                    ])
                                    ->required()
                                    ->default('pcs'),
                            ]),

                        Toggle::make('is_active')
                            ->label('Active Recipe')
                            ->default(true)
                            ->helperText('Only active recipes will be used for cost calculations'),
                    ])
                    ->columns(1),

                Section::make('Recipe Ingredients')
                    ->description('Add ingredients and their quantities for this recipe')
                    ->schema([
                        Repeater::make('items')
                            ->label('Ingredients')
                            ->relationship('items')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('ingredient_id')
                                            ->label('Ingredient')
                                            ->options(function () {
                                                return Product::where('store_id', auth()->user()->store_id)
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
                                            ->label('Quantity')
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
                                            ->label('Unit')
                                            ->options([
                                                'kg' => 'Kilogram',
                                                'g' => 'Gram',
                                                'l' => 'Liter',
                                                'ml' => 'Milliliter',
                                                'pcs' => 'Pieces',
                                                'cup' => 'Cup',
                                                'tbsp' => 'Tablespoon',
                                                'tsp' => 'Teaspoon',
                                            ])
                                            ->required()
                                            ->default('pcs'),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('unit_cost')
                                            ->label('Unit Cost')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->step(0.01)
                                            ->required()
                                            ->minValue(0)
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $quantity = $get('quantity');
                                                if ($state && $quantity) {
                                                    $set('total_cost', $quantity * $state);
                                                }
                                            }),

                                        TextInput::make('total_cost')
                                            ->label('Total Cost')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->step(0.01)
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->helperText('Calculated automatically'),
                                    ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('Add Ingredient')
                            ->collapsible()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                $state['ingredient_id'] ? Product::find($state['ingredient_id'])?->name : null
                            )
                            ->reorderable()
                            ->deleteAction(
                                fn($action) => $action->requiresConfirmation()
                            ),
                    ])
                    ->columns(1),

                Section::make('Cost Summary')
                    ->description('Recipe cost calculations')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_cost')
                                    ->label('Total Recipe Cost')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Sum of all ingredient costs'),

                                TextInput::make('cost_per_unit')
                                    ->label('Cost per Unit')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total cost ÷ yield quantity'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
