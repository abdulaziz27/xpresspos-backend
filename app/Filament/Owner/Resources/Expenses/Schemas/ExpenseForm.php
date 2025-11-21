<?php

namespace App\Filament\Owner\Resources\Expenses\Schemas;

use App\Filament\Owner\Resources\Concerns\ResolvesGlobalFilters;
use App\Models\CashSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\Money;

class ExpenseForm
{
    use ResolvesGlobalFilters;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pengeluaran')
                    ->description('Detail pengeluaran dan kategorisasi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('description')
                                    ->label('Deskripsi')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('mis: ATK, Utilitas'),

                                Select::make('category')
                                    ->label('Kategori')
                                    ->options([
                                        'office_supplies' => 'ATK',
                                        'utilities' => 'Utilitas',
                                        'rent' => 'Sewa',
                                        'marketing' => 'Marketing',
                                        'equipment' => 'Peralatan',
                                        'maintenance' => 'Perawatan',
                                        'travel' => 'Perjalanan',
                                        'food' => 'Makanan & Minuman',
                                        'other' => 'Lainnya',
                                    ])
                                    ->searchable()
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Jumlah')
                                    ->prefix('Rp')
                                    ->placeholder('50.000')
                                    ->helperText('Bisa input: 50000 atau 50.000')
                                    ->rule('required|numeric|min:0.01')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->required(),

                                DatePicker::make('expense_date')
                                    ->label('Tanggal Pengeluaran')
                                    ->default(now())
                                    ->required(),
                            ]),

                        TextInput::make('receipt_number')
                            ->label('Nomor Kwitansi')
                            ->maxLength(255)
                            ->placeholder('Nomor kwitansi atau invoice'),
                    ])
                    ->columns(1),

                Section::make('Vendor & Sesi Kas')
                    ->description('Informasi vendor dan asosiasi sesi kas')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('vendor')
                                    ->label('Vendor')
                                    ->maxLength(255)
                                    ->placeholder('Nama vendor / pemasok'),

                                Select::make('cash_session_id')
                                    ->label('Sesi Kas')
                                    ->options(fn () => static::cashSessionOptions())
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Hubungkan ke sesi kas yang sedang dibuka'),
                            ]),

                        Select::make('user_id')
                            ->label('Dicatat Oleh')
                            ->options(fn () => static::userOptionsForCurrentContext())
                            ->searchable()
                            ->preload()
                            ->default(auth()->id())
                            ->required(),
                    ])
                    ->columns(1),

                Section::make('Catatan')
                    ->description('Catatan tambahan pengeluaran')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Catatan tambahan terkait pengeluaran ini'),
                    ])
                    ->columns(1),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function cashSessionOptions(): array
    {
        $tenantId = static::currentTenantId();

        if (! $tenantId) {
            return [];
        }

        $query = CashSession::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open');

        $storeIds = static::currentStoreIds();
        if (! empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query
            ->latest('opened_at')
            ->pluck('id', 'id')
            ->toArray();
    }
}
