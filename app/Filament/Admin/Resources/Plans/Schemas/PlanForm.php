<?php

namespace App\Filament\Admin\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Paket')
                    ->description('Detail dasar paket subscription')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Paket')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Basic, Pro, Enterprise'),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('basic, pro, enterprise')
                                    ->helperText('URL-friendly identifier (huruf kecil, tanpa spasi)'),
                            ]),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Deskripsi paket dan fitur utama'),
                    ])
                    ->columns(1),

                Section::make('Harga')
                    ->description('Pricing untuk paket ini')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('price')
                                    ->label('Harga Bulanan')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Harga per bulan'),

                                TextInput::make('annual_price')
                                    ->label('Harga Tahunan')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Harga per tahun (opsional, jika ada diskon)'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Fitur & Limit (Legacy JSON)')
                    ->description('Fitur dan limit dalam format JSON (untuk backward compatibility)')
                    ->schema([
                        KeyValue::make('features')
                            ->label('Fitur')
                            ->keyLabel('Feature Code')
                            ->valueLabel('Enabled')
                            ->helperText('Daftar fitur yang tersedia (array)')
                            ->default([]),

                        KeyValue::make('limits')
                            ->label('Limit')
                            ->keyLabel('Feature')
                            ->valueLabel('Limit Value')
                            ->helperText('Limit untuk setiap fitur (object). -1 atau null = unlimited')
                            ->default([]),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Pengaturan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true)
                                    ->helperText('Nonaktifkan untuk menyembunyikan paket dari pilihan'),

                                TextInput::make('sort_order')
                                    ->label('Urutan Tampil')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Urutan tampil di landing page (angka lebih kecil = lebih atas)'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}

