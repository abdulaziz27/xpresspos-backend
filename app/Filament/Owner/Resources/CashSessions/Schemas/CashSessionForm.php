<?php

namespace App\Filament\Owner\Resources\CashSessions\Schemas;

use App\Models\User;
use App\Services\GlobalFilterService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\Money;

class CashSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Sesi')
                    ->description(fn($record) => $record ? 'Edit informasi sesi kas yang sudah ada' : 'Detail sesi kas baru - Buka Kas')
                    ->schema([
                        Select::make('store_id')
                            ->label('Cabang')
                            ->options(fn () => self::storeOptions())
                            ->default(fn () => self::getDefaultStoreId())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn($record) => $record && $record->status === 'closed')
                            ->live(),
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Kasir')
                                    ->options(fn ($get) => self::cashierOptions($get('store_id')))
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required()
                                    ->disabled(fn($record) => $record && $record->status === 'closed'),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'open' => 'Dibuka',
                                        'closed' => 'Ditutup',
                                    ])
                                    ->default('open')
                                    ->required()
                                    ->disabled(fn($record) => $record && $record->status === 'closed'),
                            ]),

                        Grid::make(1)
                            ->schema([
                                TextInput::make('opening_balance')
                                    ->label('Saldo Awal')
                                    ->prefix('Rp')
                                    ->placeholder('100.000')
                                    ->helperText('Bisa input: 100000 atau 100.000')
                                    ->rules(['required', 'numeric', 'min:0'])
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->required()
                                    ->disabled(fn($record) => $record && $record->status === 'closed')
                                    ->visible(fn($record) => !$record || $record->status === 'open'),
                            ]),
                    ])
                    ->columns(1),
                
                Section::make('Penutupan Sesi')
                    ->description('Isi informasi penutupan sesi kas')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('closing_balance')
                                    ->label('Saldo Akhir')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->helperText(fn($record) => $record && $record->status === 'open' 
                                        ? 'Saldo tunai yang tersedia saat tutup kas' 
                                        : 'Saldo tunai saat sesi ditutup')
                                    ->required(fn($record) => $record && $record->status === 'open')
                                    ->disabled(fn($record) => $record && $record->status === 'closed')
                                    ->dehydrated(fn($record) => !$record || ($record && $record->status === 'open'))
                                    ->visible(fn($record) => $record && ($record->status === 'open' || $record->status === 'closed')),

                                TextInput::make('expected_balance')
                                    ->label('Saldo Ekspektasi')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Dihitung otomatis dari saldo awal + penjualan - pengeluaran')
                                    ->visible(fn($record) => $record && $record->status === 'closed'),

                                TextInput::make('variance')
                                    ->label('Selisih')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText(fn($record) => $record && abs($record->variance ?? 0) > 0.01 
                                        ? 'Selisih antara saldo akhir dan ekspektasi (Ada selisih!)' 
                                        : 'Selisih antara saldo akhir dan ekspektasi')
                                    ->visible(fn($record) => $record && $record->status === 'closed'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record && ($record->status === 'open' || $record->status === 'closed')),

                Section::make('Ringkasan Sesi')
                    ->description('Total dan statistik sesi yang dihitung')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('cash_sales')
                                    ->label('Penjualan Tunai')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total penjualan tunai selama sesi'),

                                TextInput::make('cash_expenses')
                                    ->label('Pengeluaran Tunai')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Total pengeluaran tunai selama sesi'),

                                TextInput::make('expected_balance')
                                    ->label('Saldo Perkiraan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Saldo awal + Penjualan - Pengeluaran'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->status === 'closed'),

                Section::make('Waktu Sesi')
                    ->description('Waktu pembukaan dan penutupan sesi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('opened_at')
                                    ->label('Dibuka Pada')
                                    ->default(now())
                                    ->required()
                                    ->disabled(fn($record) => $record?->status === 'closed'),

                                DateTimePicker::make('closed_at')
                                    ->label('Ditutup Pada')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn($record) => $record?->status === 'closed'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Catatan')
                    ->description('Catatan tambahan untuk sesi ini')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan Sesi')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Catatan terkait sesi kas ini'),
                    ])
                    ->columns(1),
            ]);
    }

    protected static function storeOptions(): array
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getAvailableStores(auth()->user())
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

    protected static function cashierOptions(?string $storeId): array
    {
        $query = User::query();

        if ($storeId) {
            $query->whereHas('storeAssignments', fn ($assignment) => $assignment->where('store_id', $storeId));
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }
}
