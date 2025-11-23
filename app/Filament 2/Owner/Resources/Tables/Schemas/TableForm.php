<?php

namespace App\Filament\Owner\Resources\Tables\Schemas;

use App\Services\GlobalFilterService;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cabang')
                    ->description('Pilih cabang tempat meja ini berada')
                    ->schema([
                        Select::make('store_id')
                            ->label('Cabang')
                            ->options(fn () => static::getStoreOptions())
                            ->default(fn () => static::getDefaultStoreId())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Informasi Meja')
                    ->description('Detail meja dan konfigurasi dasar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('table_number')
                                    ->label('Nomor Meja')
                                    ->required()
                                    ->maxLength(50)
                                    ->rules([
                                        function ($get) {
                                            $storeId = $get('store_id') ?? data_get(request()->input('data'), 'store_id');
                                            $rule = Rule::unique('tables', 'table_number');

                                            if ($storeId) {
                                                $rule->where('store_id', $storeId);
                                            }

                                            if ($recordId = request()->route('record')) {
                                                $rule->ignore($recordId);
                                            }

                                            return $rule;
                                        },
                                    ]),

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

    protected static function getStoreOptions(): array
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);

        return $service->getAvailableStores()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function getDefaultStoreId(): ?string
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getCurrentStoreId()
            ?? ($globalFilter->getStoreIdsForCurrentTenant()[0] ?? null);
    }
}
