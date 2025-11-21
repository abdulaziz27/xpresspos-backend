<?php

namespace App\Filament\Owner\Resources\Members\Schemas;

use App\Models\MemberTier;
use App\Services\GlobalFilterService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Member')
                    ->description('Detail dasar member dan kontak')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('member_number')
                                    ->label('Nomor Member')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('name')
                                    ->label('Nama')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Select::make('store_id')
                            ->label('Cabang Registrasi')
                            ->options(fn () => self::storeOptions())
                            ->default(fn () => self::getDefaultStoreId())
                            ->searchable()
                            ->placeholder('Tidak ditentukan')
                            ->helperText('Opsional: catat cabang tempat member pertama kali dibuat.'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                        $tenantId = auth()->user()?->currentTenant()?->id;
                                        if ($tenantId) {
                                            $rule->where('tenant_id', $tenantId);
                                        }

                                        return $rule;
                                    }),

                                TextInput::make('phone')
                                    ->label('Telepon')
                                    ->tel()
                                    ->maxLength(20),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date_of_birth')
                                    ->label('Tanggal Lahir')
                                    ->displayFormat('d/m/Y'),

                                Select::make('tier_id')
                                    ->label('Tier Member')
                                    ->options(fn () => MemberTier::query()->ordered()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
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

                                        Select::make('store_id')
                                            ->label('Cabang Khusus')
                                            ->options(fn () => self::storeOptions())
                                            ->searchable()
                                            ->placeholder('Semua cabang'),

                                        TextInput::make('min_points')
                                            ->label('Poin Minimal')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->required(),

                                        TextInput::make('max_points')
                                            ->label('Poin Maksimal')
                                            ->numeric()
                                            ->minValue(0)
                                            ->nullable()
                                            ->helperText('Kosongkan jika tidak ada batas.'),

                                        TextInput::make('discount_percentage')
                                            ->label('Diskon (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->default(0)
                                            ->suffix('%'),

                                        ColorPicker::make('color')
                                            ->label('Warna')
                                            ->default('#6B7280'),

                                        Textarea::make('description')
                                            ->label('Deskripsi')
                                            ->rows(2)
                                            ->maxLength(1000),

                                        Toggle::make('is_active')
                                            ->label('Aktif')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $nextSort = MemberTier::query()->max('sort_order');
                                        $data['sort_order'] = ($nextSort ?? 0) + 1;

                                        return MemberTier::create($data)->getKey();
                                    }),
                            ]),

                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->maxLength(500),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->columns(1),

                Section::make('Informasi Loyalti')
                    ->description('Poin loyalti dan statistik kunjungan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('loyalty_points')
                                    ->label('Poin Loyalti')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_spent')
                                    ->label('Total Belanja')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->step(0.01)
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('visit_count')
                                    ->label('Jumlah Kunjungan')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true),

                                DatePicker::make('last_visit_at')
                                    ->label('Kunjungan Terakhir')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
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
}
