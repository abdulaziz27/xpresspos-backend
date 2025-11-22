<?php

namespace App\Filament\Owner\Resources\MemberTiers\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberTierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Tier')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Tier')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn($state, callable $set) => $set('slug', str()->slug($state))),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Select::make('store_id')
                            ->label('Cabang Khusus')
                            ->options(self::storeOptions())
                            ->searchable()
                            ->placeholder('Semua cabang')
                            ->helperText('Opsional: batasi tier hanya untuk cabang tertentu.'),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('min_points')
                                    ->label('Poin Minimal')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->default(0),

                                TextInput::make('max_points')
                                    ->label('Poin Maksimal')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable(),

                                TextInput::make('discount_percentage')
                                    ->label('Diskon (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('sort_order')
                                    ->label('Urutan Level')
                                    ->helperText('Angka kecil = level rendah, angka besar = level tinggi')
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true),
                            ]),

                        ColorPicker::make('color')
                            ->label('Warna Tier')
                            ->default('#6B7280'),
                    ])
                    ->columns(1),

                Section::make('Benefit & Deskripsi')
                    ->schema([
                        Repeater::make('benefits')
                            ->label('Benefit')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Judul Benefit')
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('details')
                                    ->label('Detail')
                                    ->rows(2)
                                    ->maxLength(1000),
                            ])
                            ->columns(1)
                            ->collapsible()
                            ->addActionLabel('Tambah Benefit')
                            ->default([]),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),
            ]);
    }

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $tenantId = $user->currentTenant()?->id;

        if (! $tenantId) {
            return [];
        }

        return \App\Models\Store::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
