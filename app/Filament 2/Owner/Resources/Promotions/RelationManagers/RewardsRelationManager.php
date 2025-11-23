<?php

namespace App\Filament\Owner\Resources\Promotions\RelationManagers;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RewardsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewards';

    protected static ?string $title = 'Hadiah Promo';

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
            Forms\Components\Select::make('reward_type')
                ->label('Jenis Hadiah')
                ->options([
                    'PCT_OFF' => 'Diskon Persentase',
                    'AMOUNT_OFF' => 'Diskon Nominal',
                    'BUY_X_GET_Y' => 'Buy X Get Y',
                    'POINTS_MULTIPLIER' => 'Poin Loyalty Kelipatan',
                ])
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('reward_value', null)),

            // PCT_OFF: Diskon Persentase
            Forms\Components\TextInput::make('percentage')
                ->label('Persentase Diskon')
                ->suffix('%')
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->placeholder('10')
                ->helperText('Masukkan persentase diskon (1-100). Contoh: 10 untuk diskon 10%')
                ->visible(fn (callable $get) => $get('reward_type') === 'PCT_OFF')
                ->required(fn (callable $get) => $get('reward_type') === 'PCT_OFF')
                ->dehydrated(true), // Ensure field is always included in form submission

            // AMOUNT_OFF: Diskon Nominal
            Forms\Components\TextInput::make('amount')
                ->label('Nominal Diskon')
                ->prefix('Rp')
                ->numeric()
                ->minValue(1)
                ->placeholder('5000')
                ->helperText('Masukkan nominal diskon dalam Rupiah. Contoh: 5000 untuk diskon Rp 5.000')
                ->visible(fn (callable $get) => $get('reward_type') === 'AMOUNT_OFF')
                ->required(fn (callable $get) => $get('reward_type') === 'AMOUNT_OFF')
                ->dehydrated(true), // Ensure field is always included in form submission

            // BUY_X_GET_Y: Buy X Get Y
            Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('buy_quantity')
                        ->label('Beli (X)')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('2')
                        ->helperText('Jumlah produk yang harus dibeli')
                        ->visible(fn (callable $get) => $get('reward_type') === 'BUY_X_GET_Y')
                        ->required(fn (callable $get) => $get('reward_type') === 'BUY_X_GET_Y')
                        ->dehydrated(true),
                    Forms\Components\TextInput::make('get_quantity')
                        ->label('Dapat (Y)')
                        ->numeric()
                        ->minValue(1)
                        ->placeholder('1')
                        ->helperText('Jumlah produk gratis yang didapat')
                        ->visible(fn (callable $get) => $get('reward_type') === 'BUY_X_GET_Y')
                        ->required(fn (callable $get) => $get('reward_type') === 'BUY_X_GET_Y')
                        ->dehydrated(true),
                    Forms\Components\Select::make('product_id')
                        ->label('Produk')
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            return Product::query()
                                ->where('status', true)
                                ->pluck('name', 'id');
                        })
                        ->placeholder('Produk yang sama')
                        ->helperText('Pilih produk gratis (kosongkan untuk produk yang sama)')
                        ->visible(fn (callable $get) => $get('reward_type') === 'BUY_X_GET_Y')
                        ->dehydrated(true),
                ])
                ->visible(fn (callable $get) => $get('reward_type') === 'BUY_X_GET_Y'),

            // POINTS_MULTIPLIER: Poin Loyalty Kelipatan
            Forms\Components\TextInput::make('multiplier')
                ->label('Kelipatan Poin')
                ->numeric()
                ->minValue(1)
                ->step(0.1)
                ->placeholder('2')
                ->helperText('Kelipatan poin yang diberikan. Contoh: 2 untuk mendapatkan 2x poin normal')
                ->visible(fn (callable $get) => $get('reward_type') === 'POINTS_MULTIPLIER')
                ->required(fn (callable $get) => $get('reward_type') === 'POINTS_MULTIPLIER')
                ->dehydrated(true), // Ensure field is always included in form submission

            // Hidden field untuk menyimpan JSON value (akan diisi otomatis saat save)
            Forms\Components\Hidden::make('reward_value')
                ->afterStateHydrated(function (Forms\Components\Hidden $component, $state, $record) {
                    // Load existing reward_value ke dynamic fields saat edit
                    if ($record && is_array($state)) {
                        $this->loadRewardFormDataIntoForm($component, $state, $record->reward_type);
                    }
                }),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reward_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'PCT_OFF' => 'Diskon %',
                        'AMOUNT_OFF' => 'Diskon Rp',
                        'BUY_X_GET_Y' => 'Buy X Get Y',
                        'POINTS_MULTIPLIER' => 'Loyalty Multiplier',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('reward_value')
                    ->label('Parameter')
                    ->formatStateUsing(function ($state, $record) {
                        return $this->formatRewardValue($state, $record);
                    })
                    ->html() // Allow HTML rendering for warning messages
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Hadiah')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['promotion_id'] = $this->getOwnerRecord()->id;
                        // Build reward_value from dynamic fields BEFORE removing them
                        $rewardValue = $this->buildRewardValue($data);
                        // Set reward_value
                        $data['reward_value'] = $rewardValue;
                        // Remove dynamic fields from data to avoid saving them directly
                        $dynamicFields = ['percentage', 'amount', 'buy_quantity', 'get_quantity', 'product_id', 'multiplier'];
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
                        if (isset($data['reward_value']) && is_array($data['reward_value'])) {
                            $data = $this->loadRewardFormData($data);
                        }
                        return $data;
                    })
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['promotion_id'] = $this->getOwnerRecord()->id;
                        // Build reward_value from dynamic fields BEFORE removing them
                        $rewardValue = $this->buildRewardValue($data);
                        // Set reward_value
                        $data['reward_value'] = $rewardValue;
                        // Remove dynamic fields from data to avoid saving them directly
                        $dynamicFields = ['percentage', 'amount', 'buy_quantity', 'get_quantity', 'product_id', 'multiplier'];
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

    protected function buildRewardValue(array $data): array
    {
        $type = $data['reward_type'] ?? null;
        
        if (!$type) {
            return [];
        }
        
        // Debug: Log the data to see what we're getting
        // \Log::info('buildRewardValue', ['type' => $type, 'data' => $data]);
        
        return match ($type) {
            'PCT_OFF' => [
                'percentage' => isset($data['percentage']) && $data['percentage'] !== '' 
                    ? (float) $data['percentage'] 
                    : 0
            ],
            'AMOUNT_OFF' => [
                'amount' => isset($data['amount']) && $data['amount'] !== '' 
                    ? (float) $data['amount'] 
                    : 0
            ],
            'BUY_X_GET_Y' => [
                'buy_quantity' => isset($data['buy_quantity']) && $data['buy_quantity'] !== '' 
                    ? (int) $data['buy_quantity'] 
                    : 1,
                'get_quantity' => isset($data['get_quantity']) && $data['get_quantity'] !== '' 
                    ? (int) $data['get_quantity'] 
                    : 1,
                'product_id' => $data['product_id'] ?? null,
            ],
            'POINTS_MULTIPLIER' => [
                'multiplier' => isset($data['multiplier']) && $data['multiplier'] !== '' 
                    ? (float) $data['multiplier'] 
                    : 1
            ],
            default => [],
        };
    }

    protected function loadRewardFormData(array $data): array
    {
        $value = $data['reward_value'] ?? [];
        $type = $data['reward_type'] ?? null;
        
        return match ($type) {
            'PCT_OFF' => array_merge($data, ['percentage' => $value['percentage'] ?? 0]),
            'AMOUNT_OFF' => array_merge($data, ['amount' => $value['amount'] ?? 0]),
            'BUY_X_GET_Y' => array_merge($data, [
                'buy_quantity' => $value['buy_quantity'] ?? 1,
                'get_quantity' => $value['get_quantity'] ?? 1,
                'product_id' => $value['product_id'] ?? null,
            ]),
            'POINTS_MULTIPLIER' => array_merge($data, ['multiplier' => $value['multiplier'] ?? 1]),
            default => $data,
        };
    }

    protected function loadRewardFormDataIntoForm(Forms\Components\Hidden $component, array $value, ?string $type): void
    {
        // This method is called by afterStateHydrated to populate dynamic form fields
        // The actual population is handled by EditAction's mutateFormDataUsing
        // This is just a placeholder for potential future use
    }

    protected function formatRewardValue($state, $record): string
    {
        // Jika state masih string JSON, decode dulu
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $state = $decoded;
            } else {
                // Jika bukan JSON valid, coba ambil dari record
                if ($record && $record->reward_value) {
                    $state = is_array($record->reward_value) ? $record->reward_value : json_decode($record->reward_value, true) ?? [];
                } else {
                    return '<span class="text-danger-500 text-xs">⚠ Data tidak valid</span>';
                }
            }
        }
        
        // Jika masih bukan array, coba ambil dari record
        if (!is_array($state)) {
            if ($record && $record->reward_value) {
                $state = is_array($record->reward_value) ? $record->reward_value : json_decode($record->reward_value, true) ?? [];
            } else {
                return '<span class="text-danger-500 text-xs">⚠ Data belum diisi</span>';
            }
        }
        
        $type = $record->reward_type ?? null;
        
        if (!$type) {
            return '<span class="text-danger-500 text-xs">⚠ Jenis hadiah tidak ditemukan</span>';
        }
        
        return match ($type) {
            'PCT_OFF' => ($percentage = $state['percentage'] ?? 0) > 0 
                ? number_format($percentage, 0) . '%' 
                : '<span class="text-warning-500 text-xs">⚠ Persentase belum diisi</span>',
            'AMOUNT_OFF' => ($amount = $state['amount'] ?? 0) > 0 
                ? 'Rp ' . number_format($amount, 0, ',', '.')
                : '<span class="text-warning-500 text-xs">⚠ Nominal belum diisi</span>',
            'BUY_X_GET_Y' => $this->formatBuyXGetY($state),
            'POINTS_MULTIPLIER' => ($multiplier = $state['multiplier'] ?? 0) > 0 
                ? number_format($multiplier, 1) . 'x' 
                : '<span class="text-warning-500 text-xs">⚠ Kelipatan belum diisi</span>',
            default => '<span class="text-gray-500 text-xs">-</span>',
        };
    }

    protected function formatBuyXGetY(array $state): string
    {
        $buyQty = $state['buy_quantity'] ?? 0;
        $getQty = $state['get_quantity'] ?? 0;
        $productId = $state['product_id'] ?? null;
        
        if ($buyQty <= 0 || $getQty <= 0) {
            return '<span class="text-warning-500 text-xs">⚠ Jumlah belum diisi (Beli: ' . ($buyQty ?: '?') . ', Dapat: ' . ($getQty ?: '?') . ')</span>';
        }
        
        $text = "Beli {$buyQty} Dapat {$getQty}";
        
        if ($productId) {
            $product = Product::find($productId);
            if ($product) {
                $text .= ' (' . $product->name . ')';
            } else {
                $text .= ' <span class="text-warning-500 text-xs">(Produk tidak ditemukan)</span>';
            }
        }
        
        return $text;
    }
}
