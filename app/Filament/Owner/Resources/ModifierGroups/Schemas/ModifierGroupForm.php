<?php

namespace App\Filament\Owner\Resources\ModifierGroups\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModifierGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Modifier')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Modifier')
                                    ->required()
                                    ->maxLength(150),
                                TextInput::make('sort_order')
                                    ->label('Urutan Tampil')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
                Section::make('Aturan Pemilihan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_select')
                                    ->label('Minimum Pilihan')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                                TextInput::make('max_select')
                                    ->label('Maksimum Pilihan')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                        Toggle::make('is_required')
                            ->label('Wajib Dipilih')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }
}

