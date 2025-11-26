<?php

namespace App\Filament\Owner\Resources\Products\Schemas;

use App\Models\Category;
use App\Filament\Owner\Resources\Concerns\HasCurrencyInput;
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
    use HasCurrencyInput;
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
                                static::currencyInput('price', 'Harga Jual', '10.500', true, 0),
                                TextInput::make('cost_price')
                                    ->label('Estimasi HPP (dari resep)')
                                    ->helperText('Harga pokok penjualan dihitung otomatis dari resep aktif. Read-only.')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->formatStateUsing(function ($state, $record) {
                                        if (!$state && $record) {
                                            // Try to get from active recipe if cost_price is null
                                            $activeRecipe = $record->getActiveRecipe();
                                            if ($activeRecipe && $activeRecipe->cost_per_unit > 0) {
                                                return 'Rp ' . number_format($activeRecipe->cost_per_unit, 0, ',', '.');
                                            }
                                        }
                                        return $state ? 'Rp ' . number_format($state, 0, ',', '.') : '-';
                                    }),
                                TextInput::make('sort_order')
                                    ->label('Urutan di Menu')
                                    ->helperText('Angka kecil akan tampil lebih dulu di menu (1, 2, 3...)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Status & Pengaturan')
                    ->description('Status produk dan pengaturan tampil')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('status')
                                    ->label('Aktif')
                                    ->default(true),

                                Toggle::make('track_inventory')
                                    ->label('Lacak Stok')
                                    ->helperText('Aktifkan untuk produk yang perlu dilacak stoknya')
                                    ->default(true),
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
                                        // Category is tenant-scoped, TenantScope will automatically filter
                                        return Category::query()
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
                                        // Category is tenant-scoped, tenant_id will be auto-set by model booted()
                                        return Category::create($data)->getKey();
                                    }),

                                FileUpload::make('image')
                                    ->label('Gambar Produk')
                                    ->image()
                                    ->imagePreviewHeight(250)
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ])
                                    ->maxSize(2048)
                                    ->disk('public')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->previewable()
                                    ->openable()
                                    ->downloadable()
                                    ->helperText('Ukuran maksimal gambar 2MB')
                                    ->rules([
                                        'image',
                                        'max:2048',
                                    ])
                                    ->validationMessages([
                                        'image' => 'File harus berupa gambar',
                                        'max' => 'Ukuran gambar tidak boleh lebih dari 2MB',
                                    ]),
                            ]),

                        Toggle::make('is_favorite')
                            ->label('Tandai sebagai Favorit')
                            ->default(false),
                    ])
                    ->columns(1),
            ]);
    }
}
