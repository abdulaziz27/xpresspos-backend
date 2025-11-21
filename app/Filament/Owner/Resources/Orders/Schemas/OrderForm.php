<?php

namespace App\Filament\Owner\Resources\Orders\Schemas;

use App\Filament\Owner\Resources\Concerns\ResolvesGlobalFilters;
use App\Models\Member;
use App\Models\Table;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Support\Money;

class OrderForm
{
    use ResolvesGlobalFilters;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pesanan')
                    ->description('Detail dasar pesanan dan informasi pelanggan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('No. Pesanan')
                                    ->disabled()
                                    ->dehydrated(false),

                                Select::make('user_id')
                                    ->label('Staf')
                                    ->options(fn () => static::userOptionsForCurrentContext())
                                    ->searchable()
                                    ->preload()
                                    ->default(auth()->id())
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('member_id')
                                    ->label('Pelanggan/Member')
                                    ->options(fn () => static::memberOptions())
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nama')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label('Telepon')
                                            ->tel()
                                            ->maxLength(20),
                                    ])
                                    ->createOptionUsing(function (array $data): string {
                                        $storeId = static::defaultStoreId();
                                        $tenantId = static::currentTenantId();

                                        if ($storeId) {
                                            $data['store_id'] = $storeId;
                                        }

                                        if ($tenantId) {
                                            $data['tenant_id'] = $tenantId;
                                        }

                                        return Member::withoutStoreScope()->create($data)->getKey();
                                    }),

                                Select::make('table_id')
                                    ->label('Meja')
                                    ->options(fn () => static::tableOptions())
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'open' => 'Terbuka',
                                'completed' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('draft')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Catatan Pesanan')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Total Pesanan')
                    ->description('Perhitungan finansial dan informasi pembayaran')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('tax_amount')
                                    ->label('Pajak')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('discount_amount')
                                    ->label('Diskon')
                                    ->prefix('Rp')
                                    ->placeholder('10.000')
                                    ->helperText('Bisa input: 10000 atau 10.000')
                                    ->rule('nullable|numeric|min:0')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->default(0),

                                TextInput::make('service_charge')
                                    ->label('Biaya Layanan')
                                    ->prefix('Rp')
                                    ->placeholder('5.000')
                                    ->helperText('Bisa input: 5000 atau 5.000')
                                    ->rule('nullable|numeric|min:0')
                                    ->dehydrateStateUsing(fn($state) => Money::parseToDecimal($state))
                                    ->default(0),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_amount')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                Select::make('payment_method')
                                    ->label('Metode Pembayaran')
                                    ->options([
                                        'cash' => 'Tunai',
                                        'credit_card' => 'Kartu Kredit',
                                        'debit_card' => 'Kartu Debit',
                                        'qris' => 'QRIS',
                                        'bank_transfer' => 'Transfer Bank',
                                        'e_wallet' => 'E-Wallet',
                                    ])
                                    ->searchable(),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Waktu')
                    ->description('Informasi waktu pesanan')
                    ->schema([
                        DateTimePicker::make('completed_at')
                            ->label('Selesai Pada')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(1)
                    ->visible(fn($record) => $record?->status === 'completed'),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function memberOptions(): array
    {
        $tenantId = static::currentTenantId();

        if (! $tenantId) {
            return [];
        }

        $query = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        $storeIds = static::currentStoreIds();
        if (! empty($storeIds)) {
            $query->where(function ($memberQuery) use ($storeIds) {
                $memberQuery
                    ->whereIn('store_id', $storeIds)
                    ->orWhereNull('store_id');
            });
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    protected static function tableOptions(): array
    {
        $tenantId = static::currentTenantId();

        if (! $tenantId) {
            return [];
        }

        $query = Table::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        $storeIds = static::currentStoreIds();
        if (! empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
