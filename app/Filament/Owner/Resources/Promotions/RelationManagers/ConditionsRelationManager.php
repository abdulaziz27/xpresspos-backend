<?php

namespace App\Filament\Owner\Resources\Promotions\RelationManagers;

use App\Models\Product;
use App\Models\MemberTier;
use App\Models\Store;
use App\Services\GlobalFilterService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conditions';

    protected static ?string $title = 'Kondisi Promo';

    /**
     * Hide from navigation - only accessible via PromotionResource relation manager.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('condition_type')
                ->label('Jenis Kondisi')
                ->options([
                    'MIN_SPEND' => 'Minimal Belanja',
                    'ITEM_INCLUDE' => 'Produk Tertentu',
                    'CUSTOMER_TIER_IN' => 'Tier Member',
                    'DOW' => 'Hari Operasional',
                    'TIME_RANGE' => 'Jam Operasional',
                    'BRANCH_IN' => 'Cabang Tertentu',
                    'NEW_CUSTOMER' => 'Pelanggan Baru',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('condition_value', null)),

            // MIN_SPEND: Minimal Belanja
            Forms\Components\TextInput::make('min_spend_amount')
                ->label('Nominal Minimal Belanja')
                ->prefix('Rp')
                ->numeric()
                ->minValue(1)
                ->placeholder('50000')
                ->helperText('Contoh: 50000 untuk minimal belanja Rp 50.000')
                ->visible(fn (callable $get) => $get('condition_type') === 'MIN_SPEND')
                ->required(fn (callable $get) => $get('condition_type') === 'MIN_SPEND')
                ->dehydrated(true), // Ensure field is always included in form submission

            // ITEM_INCLUDE: Produk Tertentu
            Forms\Components\Select::make('product_ids')
                ->label('Pilih Produk')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(function () {
                    return Product::withoutGlobalScopes()
                        ->where('status', true)
                        ->pluck('name', 'id');
                })
                ->helperText('Pilih satu atau lebih produk yang harus ada di keranjang')
                ->visible(fn (callable $get) => $get('condition_type') === 'ITEM_INCLUDE')
                ->required(fn (callable $get) => $get('condition_type') === 'ITEM_INCLUDE')
                ->dehydrated(true), // Ensure field is always included in form submission

            // CUSTOMER_TIER_IN: Tier Member
            Forms\Components\Select::make('tier_ids')
                ->label('Pilih Tier Member')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(function () {
                    return MemberTier::query()
                        ->where('is_active', true)
                        ->pluck('name', 'id');
                })
                ->helperText('Pilih tier member yang berhak mendapatkan promo ini')
                ->visible(fn (callable $get) => $get('condition_type') === 'CUSTOMER_TIER_IN')
                ->required(fn (callable $get) => $get('condition_type') === 'CUSTOMER_TIER_IN')
                ->dehydrated(true), // Ensure field is always included in form submission

            // DOW: Hari Operasional (Day of Week)
            Forms\Components\CheckboxList::make('days_of_week')
                ->label('Pilih Hari')
                ->options([
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                    0 => 'Minggu',
                ])
                ->columns(4)
                ->helperText('Pilih hari dimana promo ini berlaku')
                ->visible(fn (callable $get) => $get('condition_type') === 'DOW')
                ->required(fn (callable $get) => $get('condition_type') === 'DOW')
                ->dehydrated(true), // Ensure field is always included in form submission

            // TIME_RANGE: Jam Operasional
            Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('start_time')
                        ->label('Jam Mulai')
                        ->placeholder('08:00')
                        ->helperText('Format: HH:MM (contoh: 08:00 untuk jam 8 pagi)')
                        ->visible(fn (callable $get) => $get('condition_type') === 'TIME_RANGE')
                        ->required(fn (callable $get) => $get('condition_type') === 'TIME_RANGE')
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('end_time')
                        ->label('Jam Berakhir')
                        ->placeholder('22:00')
                        ->helperText('Format: HH:MM (contoh: 22:00 untuk jam 10 malam)')
                        ->visible(fn (callable $get) => $get('condition_type') === 'TIME_RANGE')
                        ->required(fn (callable $get) => $get('condition_type') === 'TIME_RANGE')
                        ->dehydrated(true),
                ])
                ->visible(fn (callable $get) => $get('condition_type') === 'TIME_RANGE'),

            // BRANCH_IN: Cabang Tertentu
            Forms\Components\Select::make('store_ids')
                ->label('Pilih Cabang')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(function () {
                    /** @var GlobalFilterService $globalFilter */
                    $globalFilter = app(GlobalFilterService::class);
                    return $globalFilter->getAvailableStores(auth()->user())
                        ->pluck('name', 'id');
                })
                ->helperText('Pilih cabang dimana promo ini berlaku. Kosongkan untuk semua cabang.')
                ->visible(fn (callable $get) => $get('condition_type') === 'BRANCH_IN')
                ->dehydrated(true), // Ensure field is always included in form submission

            // NEW_CUSTOMER: Pelanggan Baru (tidak perlu parameter)
            Forms\Components\Placeholder::make('new_customer_info')
                ->label('')
                ->content('Promo ini hanya berlaku untuk pelanggan baru (belum pernah melakukan transaksi).')
                ->visible(fn (callable $get) => $get('condition_type') === 'NEW_CUSTOMER'),

            // Hidden field untuk menyimpan JSON value (akan diisi otomatis saat save)
            Forms\Components\Hidden::make('condition_value')
                ->afterStateHydrated(function (Forms\Components\Hidden $component, $state, $record) {
                    // Load existing condition_value ke dynamic fields saat edit
                    if ($record && is_array($state)) {
                        $this->loadConditionFormDataIntoForm($component, $state, $record->condition_type);
                    }
                }),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('condition_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'MIN_SPEND' => 'Minimal Belanja',
                        'ITEM_INCLUDE' => 'Produk',
                        'CUSTOMER_TIER_IN' => 'Tier Member',
                        'DOW' => 'Hari',
                        'TIME_RANGE' => 'Jam',
                        'BRANCH_IN' => 'Cabang',
                        'NEW_CUSTOMER' => 'Pelanggan Baru',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('condition_value')
                    ->label('Parameter')
                    ->formatStateUsing(function ($state, $record) {
                        return $this->formatConditionValue($state, $record);
                    })
                    ->html() // Allow HTML rendering for warning messages
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kondisi')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['promotion_id'] = $this->getOwnerRecord()->id;
                        // Build condition_value from dynamic fields
                        $data['condition_value'] = $this->buildConditionValue($data);
                        // Remove dynamic fields from data to avoid saving them directly
                        $dynamicFields = ['min_spend_amount', 'product_ids', 'tier_ids', 'days_of_week', 'start_time', 'end_time', 'store_ids'];
                        foreach ($dynamicFields as $field) {
                            unset($data[$field]);
                        }
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Load existing values into form fields for editing
                        if (isset($data['condition_value']) && is_array($data['condition_value'])) {
                            $data = $this->loadConditionFormData($data);
                        }
                        return $data;
                    })
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['promotion_id'] = $this->getOwnerRecord()->id;
                        // Build condition_value from dynamic fields
                        $data['condition_value'] = $this->buildConditionValue($data);
                        // Remove dynamic fields from data to avoid saving them directly
                        $dynamicFields = ['min_spend_amount', 'product_ids', 'tier_ids', 'days_of_week', 'start_time', 'end_time', 'store_ids'];
                        foreach ($dynamicFields as $field) {
                            unset($data[$field]);
                        }
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function buildConditionValue(array $data): array
    {
        $type = $data['condition_type'] ?? null;
        
        if (!$type) {
            return [];
        }
        
        return match ($type) {
            'MIN_SPEND' => [
                'amount' => isset($data['min_spend_amount']) && $data['min_spend_amount'] !== '' 
                    ? (float) $data['min_spend_amount'] 
                    : 0
            ],
            'ITEM_INCLUDE' => ['product_ids' => $data['product_ids'] ?? []],
            'CUSTOMER_TIER_IN' => ['tier_ids' => $data['tier_ids'] ?? []],
            'DOW' => ['days' => $data['days_of_week'] ?? []],
            'TIME_RANGE' => [
                'start_time' => isset($data['start_time']) && $data['start_time'] !== '' 
                    ? $data['start_time'] 
                    : '08:00',
                'end_time' => isset($data['end_time']) && $data['end_time'] !== '' 
                    ? $data['end_time'] 
                    : '22:00',
            ],
            'BRANCH_IN' => ['store_ids' => $data['store_ids'] ?? []],
            'NEW_CUSTOMER' => [],
            default => [],
        };
    }

    protected function loadConditionFormData(array $data): array
    {
        $value = $data['condition_value'] ?? [];
        $type = $data['condition_type'] ?? null;
        
        return match ($type) {
            'MIN_SPEND' => array_merge($data, ['min_spend_amount' => $value['amount'] ?? 0]),
            'ITEM_INCLUDE' => array_merge($data, ['product_ids' => $value['product_ids'] ?? []]),
            'CUSTOMER_TIER_IN' => array_merge($data, ['tier_ids' => $value['tier_ids'] ?? []]),
            'DOW' => array_merge($data, ['days_of_week' => $value['days'] ?? []]),
            'TIME_RANGE' => array_merge($data, [
                'start_time' => $value['start_time'] ?? '08:00',
                'end_time' => $value['end_time'] ?? '22:00',
            ]),
            'BRANCH_IN' => array_merge($data, ['store_ids' => $value['store_ids'] ?? []]),
            default => $data,
        };
    }

    protected function loadConditionFormDataIntoForm(Forms\Components\Hidden $component, array $value, ?string $type): void
    {
        // This method is called by afterStateHydrated to populate dynamic form fields
        // The actual population is handled by EditAction's mutateFormDataUsing
        // This is just a placeholder for potential future use
    }

    protected function formatConditionValue($state, $record): string
    {
        // Jika state masih string JSON, decode dulu
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            } else {
                // Jika bukan JSON valid, coba ambil dari record
                if ($record && $record->condition_value) {
                    $state = is_array($record->condition_value) ? $record->condition_value : json_decode($record->condition_value, true) ?? [];
                } else {
                    return '<span class="text-danger-500 text-xs">⚠ Data tidak valid</span>';
                }
            }
        }
        
        // Jika masih bukan array, coba ambil dari record
        if (!is_array($state)) {
            if ($record && $record->condition_value) {
                $state = is_array($record->condition_value) ? $record->condition_value : json_decode($record->condition_value, true) ?? [];
            } else {
                return '<span class="text-danger-500 text-xs">⚠ Data belum diisi</span>';
            }
        }
        
        $type = $record->condition_type ?? null;
        
        if (!$type) {
            return '<span class="text-danger-500 text-xs">⚠ Jenis kondisi tidak ditemukan</span>';
        }
        
        return match ($type) {
            'MIN_SPEND' => ($amount = $state['amount'] ?? 0) > 0 
                ? 'Rp ' . number_format($amount, 0, ',', '.')
                : '<span class="text-warning-500 text-xs">⚠ Nominal belum diisi</span>',
            'ITEM_INCLUDE' => $this->formatItemInclude($state),
            'CUSTOMER_TIER_IN' => $this->formatCustomerTierIn($state),
            'DOW' => $this->formatDow($state),
            'TIME_RANGE' => $this->formatTimeRange($state),
            'BRANCH_IN' => $this->formatBranchIn($state),
            'NEW_CUSTOMER' => 'Pelanggan Baru',
            default => '<span class="text-gray-500 text-xs">-</span>',
        };
    }

    protected function formatItemInclude(array $state): string
    {
        $productIds = $state['product_ids'] ?? [];
        if (empty($productIds)) {
            return '<span class="text-warning-500 text-xs">⚠ Produk belum dipilih</span>';
        }
        $count = count($productIds);
        if ($count <= 3) {
            $products = Product::withoutGlobalScopes()
                ->whereIn('id', $productIds)
                ->pluck('name');
            if ($products->isEmpty()) {
                return '<span class="text-warning-500 text-xs">⚠ Produk tidak ditemukan</span>';
            }
            return $products->join(', ');
        }
        return $count . ' produk';
    }

    protected function formatCustomerTierIn(array $state): string
    {
        $tierIds = $state['tier_ids'] ?? [];
        if (empty($tierIds)) {
            return '<span class="text-warning-500 text-xs">⚠ Tier belum dipilih</span>';
        }
        $count = count($tierIds);
        if ($count <= 3) {
            $tiers = MemberTier::whereIn('id', $tierIds)->pluck('name');
            if ($tiers->isEmpty()) {
                return '<span class="text-warning-500 text-xs">⚠ Tier tidak ditemukan</span>';
            }
            return $tiers->join(', ');
        }
        return $count . ' tier';
    }

    protected function formatDow(array $state): string
    {
        $days = $state['days'] ?? [];
        if (empty($days)) {
            return '<span class="text-warning-500 text-xs">⚠ Hari belum dipilih</span>';
        }
        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
        $names = collect($days)->map(fn($d) => $dayNames[$d] ?? $d)->join(', ');
        return $names ?: '<span class="text-warning-500 text-xs">⚠ Hari tidak valid</span>';
    }

    protected function formatTimeRange(array $state): string
    {
        $start = $state['start_time'] ?? '';
        $end = $state['end_time'] ?? '';
        if (!$start || !$end) {
            return '<span class="text-warning-500 text-xs">⚠ Jam belum diisi</span>';
        }
        return $start . ' - ' . $end;
    }

    protected function formatBranchIn(array $state): string
    {
        $storeIds = $state['store_ids'] ?? [];
        if (empty($storeIds)) {
            return '<span class="text-warning-500 text-xs">⚠ Cabang belum dipilih</span>';
        }
        $count = count($storeIds);
        if ($count <= 3) {
            $stores = Store::whereIn('id', $storeIds)->pluck('name');
            if ($stores->isEmpty()) {
                return '<span class="text-warning-500 text-xs">⚠ Cabang tidak ditemukan</span>';
            }
            return $stores->join(', ');
        }
        return $count . ' cabang';
    }
}
