<?php

namespace App\Filament\Owner\Resources\PurchaseOrders\RelationManagers;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Support\Currency;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Detail Order';

    public function form(Schema $schema): Schema
    {
        $po = $this->getOwnerRecord();
        $isDraft = !$po || $po->status === PurchaseOrder::STATUS_DRAFT;
        $isApproved = $po && $po->status === PurchaseOrder::STATUS_APPROVED;
        $isReceived = $po && $po->status === PurchaseOrder::STATUS_RECEIVED;
        $isClosed = $po && $po->status === PurchaseOrder::STATUS_CLOSED;
        $isCancelled = $po && $po->status === PurchaseOrder::STATUS_CANCELLED;
        $isReadOnly = $isReceived || $isClosed || $isCancelled;

        return $schema->components([
            Forms\Components\Select::make('inventory_item_id')
                ->label('Bahan')
                ->options(function () {
                    return InventoryItem::query()
                        ->where('status', 'active')
                        ->with('uom')
                        ->get()
                        ->mapWithKeys(function ($item) {
                            return [$item->id => $item->name . ' (' . ($item->uom?->code ?? '-') . ')'];
                        });
                })
                ->searchable()
                ->preload()
                ->required()
                ->disabled($isReadOnly)
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $inventoryItem = InventoryItem::with('uom')->find($state);
                        if ($inventoryItem && $inventoryItem->uom_id) {
                            // uom_id will be auto-set by model event, but set here for UI consistency
                            $set('uom_id', $inventoryItem->uom_id);
                        }
                    }
                }),

            Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('quantity_ordered')
                        ->label('Qty Dipesan')
                        ->numeric()
                        ->inputMode('decimal')
                        ->required()
                        ->minValue(0.001)
                        ->step(0.001)
                        ->disabled($isReadOnly)
                        ->live()
                        ->formatStateUsing(function ($state) {
                            // Format display: remove unnecessary .000 for whole numbers
                            if ($state === null || $state === '') {
                                return null;
                            }
                            
                            $value = (float) $state;
                            
                            // If it's a whole number, return without decimals
                            if ($value == floor($value)) {
                                return (string) (int) $value;
                            }
                            
                            // Otherwise, return with appropriate decimal places (max 3)
                            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                        })
                        ->dehydrateStateUsing(function ($state) {
                            // Ensure proper numeric value is saved
                            // Fix issue where "10" becomes "10.000" due to locale formatting
                            if ($state === null || $state === '') {
                                return null;
                            }
                            
                            // Convert to string and clean
                            $str = trim((string) $state);
                            
                            // If it's already a valid number, use it directly
                            if (is_numeric($str) && !str_contains($str, '.')) {
                                return (float) $str;
                            }
                            
                            // Handle dot separator - check if it's thousands or decimal
                            if (str_contains($str, '.')) {
                                $parts = explode('.', $str);
                                
                                // If exactly 2 parts and second part is "000", 
                                // it's likely thousands separator (Indonesian format)
                                // But if first part is small (< 1000), it might be auto-formatting issue
                                if (count($parts) === 2) {
                                    $firstPart = $parts[0];
                                    $secondPart = $parts[1];
                                    
                                    // If second part is "000" and first part is small, treat as formatting issue
                                    if ($secondPart === '000' && is_numeric($firstPart) && (float) $firstPart < 1000) {
                                        return (float) $firstPart;
                                    }
                                    
                                    // If second part has 1-2 digits, it's decimal
                                    if (strlen($secondPart) <= 2 && is_numeric($firstPart) && is_numeric($secondPart)) {
                                        return (float) ($firstPart . '.' . $secondPart);
                                    }
                                    
                                    // Otherwise, remove dots (thousands separator)
                                    $cleaned = str_replace('.', '', $str);
                                    return (float) $cleaned;
                                } else {
                                    // Multiple dots = thousands separators, remove all
                                    $cleaned = str_replace('.', '', $str);
                                    return (float) $cleaned;
                                }
                            }
                            
                            // Handle comma as decimal separator
                            if (str_contains($str, ',')) {
                                $str = str_replace(',', '.', $str);
                            }
                            
                            return (float) $str;
                        })
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalculate total_cost
                            $unitCost = $get('unit_cost') ?? 0;
                            if ($state && $unitCost) {
                                $set('total_cost', round($state * $unitCost, 2));
                            }
                        })
                        ->helperText('Jumlah yang dipesan (contoh: 10 untuk 10 kg)'),

                    Forms\Components\TextInput::make('quantity_received')
                        ->label('Qty Diterima')
                        ->numeric()
                        ->inputMode('decimal')
                        ->default(0)
                        ->minValue(0)
                        ->step(0.001)
                        ->disabled($isReadOnly || $isDraft)
                        ->formatStateUsing(function ($state) {
                            // Format display: remove unnecessary .000 for whole numbers
                            if ($state === null || $state === '' || $state == 0) {
                                return '0';
                            }
                            
                            $value = (float) $state;
                            
                            // If it's a whole number, return without decimals
                            if ($value == floor($value)) {
                                return (string) (int) $value;
                            }
                            
                            // Otherwise, return with appropriate decimal places (max 3)
                            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                        })
                        ->dehydrateStateUsing(function ($state) {
                            // Ensure proper numeric value is saved
                            if ($state === null || $state === '') {
                                return 0;
                            }
                            
                            // Convert to string and clean
                            $str = trim((string) $state);
                            
                            // If it's already a valid number without dot, use it directly
                            if (is_numeric($str) && !str_contains($str, '.')) {
                                return (float) $str;
                            }
                            
                            // Handle dot separator
                            if (str_contains($str, '.')) {
                                $parts = explode('.', $str);
                                
                                if (count($parts) === 2) {
                                    $firstPart = $parts[0];
                                    $secondPart = $parts[1];
                                    
                                    // If second part is "000" and first part is small, treat as formatting issue
                                    if ($secondPart === '000' && is_numeric($firstPart) && (float) $firstPart < 1000) {
                                        return (float) $firstPart;
                                    }
                                    
                                    // If second part has 1-2 digits, it's decimal
                                    if (strlen($secondPart) <= 2 && is_numeric($firstPart) && is_numeric($secondPart)) {
                                        return (float) ($firstPart . '.' . $secondPart);
                                    }
                                    
                                    // Otherwise, remove dots (thousands separator)
                                    $cleaned = str_replace('.', '', $str);
                                    return (float) $cleaned;
                                } else {
                                    // Multiple dots = thousands separators
                                    $cleaned = str_replace('.', '', $str);
                                    return (float) $cleaned;
                                }
                            }
                            
                            // Handle comma as decimal separator
                            if (str_contains($str, ',')) {
                                $str = str_replace(',', '.', $str);
                            }
                            
                            return (float) $str;
                        })
                        ->helperText(fn () => $isApproved 
                            ? 'Jumlah yang diterima (boleh diisi saat status approved/received)'
                            : 'Jumlah yang diterima'),
                ]),

            Grid::make(2)
                ->schema([
                    // UOM display (read-only, from inventory item)
                    Placeholder::make('uom_display')
                        ->label('Satuan')
                        ->content(function ($record, callable $get) {
                            $inventoryItemId = $get('inventory_item_id') ?? $record?->inventory_item_id;
                            if ($inventoryItemId) {
                                $inventoryItem = InventoryItem::with('uom')->find($inventoryItemId);
                                return $inventoryItem?->uom?->code ?? $inventoryItem?->uom?->name ?? '-';
                            }
                            return '-';
                        }),

                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Biaya Satuan')
                        ->numeric()
                        ->prefix('Rp')
                        ->step(0.0001)
                        ->required()
                        ->disabled($isReadOnly)
                        ->live(debounce: 500)
                        ->formatStateUsing(function ($state) {
                            // Don't format during typing to prevent losing last digit
                            // Only return the raw value for display during input
                            return $state;
                        })
                        ->dehydrateStateUsing(function ($state) {
                            // Parse input correctly using Money helper only when saving
                            if ($state === null || $state === '') {
                                return null;
                            }
                            // Use Money::parseToDecimal for consistent parsing
                            return (float) Money::parseToDecimal($state, 4);
                        })
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalculate total_cost with debounced update
                            // Parse only when we have a complete value
                            $quantity = $get('quantity_ordered') ?? 0;
                            if ($state && $quantity) {
                                // Try to parse, but if it fails, use raw value
                                try {
                                    $parsedState = is_string($state) && $state !== ''
                                        ? (float) Money::parseToDecimal($state, 4)
                                        : (float) $state;
                                    $set('total_cost', round($quantity * $parsedState, 2));
                                } catch (\Exception $e) {
                                    // If parsing fails, skip update
                                }
                            }
                        })
                        ->helperText('Biaya per satuan (contoh: 15000 atau 15.000)'),
                ]),

            Forms\Components\TextInput::make('total_cost')
                ->label('Total Cost')
                ->prefix('Rp')
                ->disabled()
                ->dehydrated(true)
                ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                ->helperText('Dihitung otomatis: Qty Dipesan × Biaya Satuan'),

            // Hidden field for uom_id (set automatically by model)
            Forms\Components\Hidden::make('uom_id')
                ->dehydrated(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $po = $this->getOwnerRecord();
        $isReceived = $po && $po->status === PurchaseOrder::STATUS_RECEIVED;
        $isClosed = $po && $po->status === PurchaseOrder::STATUS_CLOSED;
        $isCancelled = $po && $po->status === PurchaseOrder::STATUS_CANCELLED;
        $isReadOnly = $isReceived || $isClosed || $isCancelled;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Bahan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn($record) => $record->inventoryItem?->sku ? 'SKU: ' . $record->inventoryItem->sku : null),

                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('uom.name')
                    ->label('Satuan')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity_ordered')
                    ->label('Qty Dipesan')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }
                        
                        $value = (float) $state;
                        
                        // If it's a whole number, display without decimals
                        if ($value == floor($value)) {
                            return (string) (int) $value;
                        }
                        
                        // Otherwise, display with appropriate decimal places (max 3, remove trailing zeros)
                        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                    })
                    ->sortable()
                    ->alignEnd()
                    ->suffix(fn($record) => ' ' . ($record->uom?->code ?? $record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Qty Diterima')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '0';
                        }
                        
                        $value = (float) $state;
                        
                        // If it's a whole number, display without decimals
                        if ($value == floor($value)) {
                            return (string) (int) $value;
                        }
                        
                        // Otherwise, display with appropriate decimal places (max 3, remove trailing zeros)
                        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                    })
                    ->sortable()
                    ->alignEnd()
                    ->color(function ($record) {
                        if (!$record->quantity_ordered || $record->quantity_ordered == 0) {
                            return null;
                        }
                        $received = $record->quantity_received ?? 0;
                        $ordered = $record->quantity_ordered;
                        if ($received >= $ordered) {
                            return 'success';
                        }
                        if ($received > 0) {
                            return 'warning';
                        }
                        return 'gray';
                    })
                    ->suffix(fn($record) => ' ' . ($record->uom?->code ?? $record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Biaya Satuan')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Item')
                    ->visible(!$isReadOnly),
            ])
            ->actions([
                EditAction::make()
                    ->visible(!$isReadOnly),
                DeleteAction::make()
                    ->visible(!$isReadOnly),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(!$isReadOnly),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->with(['inventoryItem.uom', 'uom'])
                            ->orderBy('created_at', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function canCreate(): bool
    {
        $po = $this->getOwnerRecord();
        return !$po || !in_array($po->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED]);
    }

    public function canEdit($record): bool
    {
        $po = $this->getOwnerRecord();
        return !$po || !in_array($po->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED]);
    }

    public function canDelete($record): bool
    {
        $po = $this->getOwnerRecord();
        return !$po || !in_array($po->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED]);
    }
}


