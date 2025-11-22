<?php

namespace App\Filament\Owner\Resources\Discounts\Schemas;

use App\Filament\Owner\Resources\Discounts\DiscountResource;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiscountForm
{
    public static function configure(Schema $schema): Schema
    {
        $storeOptions = DiscountResource::storeOptions();

        return $schema
            ->components([
                Section::make('Informasi Diskon')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Diskon')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: Diskon Karyawan, Diskon Owner, Komplain'),
                                
                                Select::make('type')
                                    ->label('Tipe Diskon')
                                    ->required()
                                    ->options([
                                        'percentage' => 'Persentase (%)',
                                        'fixed' => 'Nominal (Rp)',
                                    ])
                                    ->default('percentage')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('value', null);
                                    }),
                            ]),
                        
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Deskripsi singkat (opsional)')
                            ->helperText('Keterangan kapan dan bagaimana diskon ini berlaku'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('value')
                                    ->label('Nilai Diskon')
                                    ->required()
                                    ->numeric()
                                    ->prefix(fn (callable $get) => $get('type') === 'fixed' ? 'Rp' : null)
                                    ->suffix(fn (callable $get) => $get('type') === 'percentage' ? '%' : null)
                                    ->placeholder(fn (callable $get) => $get('type') === 'percentage' ? '10' : '50000')
                                    ->helperText(fn (callable $get) => $get('type') === 'percentage' 
                                        ? 'Masukkan persentase (contoh: 10 untuk 10%)' 
                                        : 'Masukkan nominal (contoh: 50000 atau 50.000)')
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0',
                                        fn (callable $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if ($get('type') === 'percentage' && $value > 100) {
                                                $fail('Persentase tidak boleh lebih dari 100%.');
                                            }
                                        },
                                    ])
                                    ->dehydrateStateUsing(function ($state, callable $get) {
                                        // Only parse if it's fixed amount (Rp), not percentage
                                        if ($get('type') === 'fixed') {
                                            return Money::parseToDecimal($state);
                                        }
                                        return $state;
                                    }),

                                DatePicker::make('expired_date')
                                    ->label('Tanggal Kadaluarsa')
                                    ->placeholder('Kosongkan jika tanpa batas waktu')
                                    ->minDate(now())
                                    ->helperText('Opsional: Atur kapan diskon ini berakhir'),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                Select::make('store_id')
                                    ->label('Berlaku untuk Toko')
                                    ->options($storeOptions)
                                    ->searchable()
                                    ->placeholder('Semua Toko')
                                    ->helperText('Pilih toko tertentu atau kosongkan untuk semua toko.')
                                    ->native(false),

                                Toggle::make('status')
                                    ->label('Status Aktif')
                                    ->helperText('Hanya diskon aktif yang bisa digunakan di POS')
                                    ->default(true)
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        // Convert enum to boolean for toggle
                                        if ($record && is_string($state)) {
                                            $component->state($state === 'active');
                                        }
                                    })
                                    ->dehydrateStateUsing(fn ($state) => $state ? 'active' : 'inactive'),
                            ]),
                    ]),
            ]);
    }
}