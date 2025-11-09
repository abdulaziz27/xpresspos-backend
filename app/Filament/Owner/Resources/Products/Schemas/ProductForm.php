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
use App\Support\Money;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Produk')
                    ->description('Detail dasar produk dan harga')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Produk')
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
                                    ->label('Kode Produk')
                                    ->helperText('Kode unik untuk identifikasi produk (contoh: ESP001, CAP001)')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash(),
                            ]),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(1000),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('price')
                                    ->label('Harga')
                                    ->required()
                                    ->prefix('Rp')
                                    ->helperText('Bisa input: 10500 atau 10.500')
                                    ->placeholder('10.500')
                                    ->rules(['required', 'numeric', 'min:0'])
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state)),

                                TextInput::make('cost_price')
                                    ->label('Harga Pokok')
                                    ->prefix('Rp')
                                    ->helperText('Bisa input: 8000 atau 8.000')
                                    ->placeholder('8.000')
                                    ->rules(['nullable', 'numeric', 'min:0'])
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state)),

                                TextInput::make('sort_order')
                                    ->label('Urutan di Menu')
                                    ->helperText('Angka kecil akan tampil lebih dulu di menu (1, 2, 3...)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Manajemen Stok')
                    ->description('Pengaturan pelacakan stok dan persediaan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('track_inventory')
                                    ->label('Lacak Stok')
                                    ->default(true)
                                    ->live(),

                                Toggle::make('status')
                                    ->label('Aktif')
                                    ->default(true),
                            ]),

                        Group::make([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('stock')
                                        ->label('Stok')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->visible(fn(callable $get) => $get('track_inventory')),

                                    TextInput::make('min_stock_level')
                                        ->label('Batas Minimum Stok')
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->visible(fn(callable $get) => $get('track_inventory')),
                                ])
                                ->visible(fn(callable $get) => $get('track_inventory')),
                        ]),
                    ])
                    ->columns(1),

                Section::make('Kategori & Media')
                    ->description('Kategorisasi produk dan gambar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('category_id')
                                    ->label('Kategori')
                                    ->required()
                                    ->options(function () {
                                        $user = auth()->user();
                                        $storeId = $user ? $user->store_id : null;

                                        return Category::query()
                                            ->when($storeId, fn($query) => $query->where('store_id', $storeId))
                                            ->where('status', true)
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nama')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('description')
                                            ->label('Deskripsi')
                                            ->maxLength(500),
                                        Toggle::make('status')
                                            ->label('Aktif')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $user = auth()->user();
                                        $data['store_id'] = $user ? $user->store_id : null;
                                        return Category::create($data)->getKey();
                                    }),

                                FileUpload::make('image')
                                    ->label('Gambar Produk')
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
                            ->label('Tandai sebagai Favorit')
                            ->default(false),
                    ])
                    ->columns(1),
            ]);
    }
}
