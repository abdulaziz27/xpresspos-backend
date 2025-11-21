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
use App\Support\Currency;
use App\Filament\Owner\Resources\Concerns\HasCurrencyInput;

class CashSessionForm
{
    use HasCurrencyInput;
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
                                static::currencyInput('opening_balance', 'Saldo Awal', '100.000', true, 0)
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
                                // Closing balance - editable when open, display formatted when closed
                                static::currencyInput('closing_balance', 'Saldo Akhir', '500.000', false, 0)
                                    ->helperText(fn($record) => $record && $record->status === 'open' 
                                        ? 'Bisa input: 500000 atau 500.000' 
                                        : 'Saldo tunai saat sesi ditutup')
                                    ->required(fn($record) => $record && $record->status === 'open')
                                    ->disabled(fn($record) => $record && $record->status === 'closed')
                                    ->dehydrated(fn($record) => !$record || ($record && $record->status === 'open'))
                                    ->visible(fn($record) => $record && $record->status === 'open'),

                                // Closing balance display - formatted for closed sessions
                                static::currencyDisplay('closing_balance_display', 'Saldo Akhir', 'Saldo tunai saat sesi ditutup')
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        $value = $record && $record->closing_balance 
                                            ? (float) $record->closing_balance 
                                            : 0;
                                        $component->state(\App\Support\Currency::rupiah($value));
                                    })
                                    ->visible(fn($record) => $record && $record->status === 'closed'),

                                static::currencyDisplay('expected_balance_display', 'Saldo Ekspektasi')
                                    ->helperText('Dihitung otomatis dari saldo awal + penjualan - pengeluaran')
                                    ->visible(fn($record) => $record && $record->status === 'closed'),

                                static::currencyDisplay('variance_display', 'Selisih')
                                    ->helperText(function($record) {
                                        if ($record && abs($record->variance ?? 0) > 0.01) {
                                            return 'Selisih antara saldo akhir dan ekspektasi (Ada selisih!)';
                                        }
                                        return 'Selisih antara saldo akhir dan ekspektasi';
                                    })
                                    ->visible(fn($record) => $record && $record->status === 'closed'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record && ($record->status === 'open' || $record->status === 'closed')),

                Section::make('Ringkasan Sesi (Otomatis)')
                    ->description('Total dan statistik sesi yang dihitung otomatis dari data transaksi')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                static::currencyDisplay('cash_sales', 'Penjualan Tunai')
                                    ->helperText('Dihitung otomatis dari pembayaran tunai selama sesi'),

                                static::currencyDisplay('cash_expenses', 'Pengeluaran Tunai')
                                    ->helperText('Dihitung otomatis dari pengeluaran (expenses) terkait sesi'),

                                static::currencyDisplay('expected_balance', 'Saldo Perkiraan')
                                    ->helperText('Dihitung otomatis: Saldo Awal + Penjualan - Pengeluaran'),
                            ]),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record && ($record->status === 'open' || $record->status === 'closed')),

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
