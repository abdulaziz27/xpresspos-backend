<?php

namespace App\Filament\Owner\Resources\Tables\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Meja')
                    ->description('Detail meja dan konfigurasi dasar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('table_number')
                                    ->label('Nomor Meja')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        $user = auth()->user();
                                        if ($user && $user->store_id) {
                                            $rule->where('store_id', $user->store_id);
                                        }
                                        return $rule;
                                    }),

                                TextInput::make('name')
                                    ->label('Nama Meja')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('mis: Meja 1, Booth VIP A'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('capacity')
                                    ->label('Kapasitas')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(50)
                                    ->default(4)
                                    ->suffix('orang'),

                                Select::make('location')
                                    ->label('Lokasi')
                                    ->options([
                                        'indoor' => 'Dalam Ruangan',
                                        'outdoor' => 'Luar Ruangan',
                                        'terrace' => 'Teras',
                                        'vip' => 'Area VIP',
                                        'bar' => 'Area Bar',
                                        'other' => 'Lainnya',
                                    ])
                                    ->searchable()
                                    ->default('indoor'),
                            ]),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Catatan khusus untuk meja ini'),
                    ])
                    ->columns(1),

                Section::make('Status Meja')
                    ->description('Pengaturan ketersediaan dan status meja')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'available' => 'Tersedia',
                                        'occupied' => 'Terisi',
                                        'reserved' => 'Direservasi',
                                        'maintenance' => 'Perawatan',
                                        'cleaning' => 'Pembersihan',
                                    ])
                                    ->default('available')
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true)
                                    ->helperText('Meja nonaktif tidak akan tampil di POS'),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
