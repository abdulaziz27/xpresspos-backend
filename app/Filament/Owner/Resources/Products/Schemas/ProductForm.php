<?php

namespace App\Filament\Owner\Resources\Products\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->description('Basic product details and pricing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                        if ($operation !== 'create') {
                                            return;
                                        }

                                        $set('sku', strtoupper(str_replace(' ', '-', $state)));
                                    }),

                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash(),
                            ]),

                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0),

                                TextInput::make('cost_price')
                                    ->label('Cost Price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Inventory Management')
                    ->description('Stock tracking and inventory settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('track_inventory')
                                    ->label('Track Inventory')
                                    ->default(true)
                                    ->live(),

                                Toggle::make('status')
                                    ->label('Active')
                                    ->default(true),
                            ]),

                        Group::make([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('stock')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->visible(fn(callable $get) => $get('track_inventory')),

                                    TextInput::make('min_stock_level')
                                        ->label('Minimum Stock Level')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->visible(fn(callable $get) => $get('track_inventory')),
                                ])
                                ->visible(fn(callable $get) => $get('track_inventory')),
                        ]),
                    ])
                    ->columns(1),

                Section::make('Category & Media')
                    ->description('Product categorization and images')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(function () {
                                        $storeId = auth()->user()?->currentStoreId();

                                        return Category::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('is_active', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('description')
                                            ->maxLength(500),
                                        Toggle::make('is_active')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $data['store_id'] = auth()->user()?->currentStoreId();
                                        return Category::create($data)->getKey();
                                    }),

                                FileUpload::make('image')
                                    ->label('Product Image')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ])
                                    ->maxSize(2048)
                                    ->directory('products')
                                    ->visibility('public'),
                            ]),

                        Toggle::make('is_favorite')
                            ->label('Mark as Favorite')
                            ->default(false),
                    ])
                    ->columns(1),
            ]);
    }
}
