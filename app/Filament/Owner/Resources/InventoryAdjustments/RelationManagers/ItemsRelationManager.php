<?php

namespace App\Filament\Owner\Resources\InventoryAdjustments\RelationManagers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Support\Currency;
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

    protected static ?string $title = 'Detail Penyesuaian';

    public function form(Schema $schema): Schema
    {
        $adjustment = $this->getOwnerRecord();
        $isReadOnly = $adjustment && in_array($adjustment->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED]);

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
                ->afterStateUpdated(function ($state, callable $set, callable $get) use ($adjustment) {
                    if ($state && $adjustment && $adjustment->store_id) {
                        // Get system_qty from StockLevel
                        $stockLevel = StockLevel::withoutGlobalScopes()
                            ->where('store_id', $adjustment->store_id)
                            ->where('inventory_item_id', $state)
                            ->first();
                        
                        $systemQty = $stockLevel ? $stockLevel->current_stock : 0;
                        $set('system_qty', $systemQty);

                        // Get unit_cost from StockLevel.average_cost or inventoryItem.default_cost
                        $inventoryItem = InventoryItem::find($state);
                        $unitCost = $stockLevel && $stockLevel->average_cost > 0
                            ? $stockLevel->average_cost
                            : ($inventoryItem?->default_cost ?? 0);
                        $set('unit_cost', $unitCost);
                    }
                }),

            Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('system_qty')
                        ->label('Qty Sistem')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->default(0)
                        ->step(0.001)
                        ->formatStateUsing(function ($state) {
                            if ($state === null || $state === '') {
                                return '0';
                            }
                            $value = (float) $state;
                            if ($value == floor($value)) {
                                return (string) (int) $value;
                            }
                            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                        })
                        ->helperText('Jumlah stok menurut sistem (read-only)'),

                    Forms\Components\TextInput::make('counted_qty')
                        ->label('Qty Hasil Hitung')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->step(0.001)
                        ->disabled($isReadOnly)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $systemQty = $get('system_qty') ?? 0;
                            $countedQty = $state ?? 0;
                            $differenceQty = $countedQty - $systemQty;
                            $set('difference_qty', $differenceQty);

                            // Recalculate total_cost
                            $unitCost = $get('unit_cost') ?? 0;
                            if ($unitCost > 0) {
                                $set('total_cost', abs($differenceQty) * $unitCost);
                            }
                        })
                        ->helperText('Jumlah stok hasil pengecekan fisik'),
                ]),

            Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('difference_qty')
                        ->label('Selisih')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(true)
                        ->step(0.001)
                        ->formatStateUsing(function ($state) {
                            if ($state === null || $state === '') {
                                return '0';
                            }
                            $value = (float) $state;
                            if ($value == floor($value)) {
                                return (string) (int) $value;
                            }
                            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                        })
                        ->helperText('Dihitung otomatis: Qty Hitung - Qty Sistem'),

                    Forms\Components\TextInput::make('unit_cost')
                        ->label('Biaya Satuan')
                        ->numeric()
                        ->prefix('Rp')
                        ->disabled()
                        ->dehydrated(true)
                        ->step(0.0001)
                        ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                        ->helperText('Dari StockLevel.average_cost atau InventoryItem.default_cost (read-only)'),
                ]),

            Forms\Components\TextInput::make('total_cost')
                ->label('Total Biaya')
                ->numeric()
                ->prefix('Rp')
                ->step(0.01)
                ->disabled()
                ->dehydrated(true)
                ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                ->helperText('Dihitung otomatis: |Selisih| × Biaya Satuan'),
        ]);
    }

    public function table(Table $table): Table
    {
        $adjustment = $this->getOwnerRecord();
        $isReadOnly = $adjustment && in_array($adjustment->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED]);

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

                Tables\Columns\TextColumn::make('system_qty')
                    ->label('Qty Sistem')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '0';
                        }
                        $value = (float) $state;
                        if ($value == floor($value)) {
                            return (string) (int) $value;
                        }
                        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                    })
                    ->sortable()
                    ->alignEnd()
                    ->suffix(fn($record) => ' ' . ($record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('counted_qty')
                    ->label('Qty Hitung')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '0';
                        }
                        $value = (float) $state;
                        if ($value == floor($value)) {
                            return (string) (int) $value;
                        }
                        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                    })
                    ->sortable()
                    ->alignEnd()
                    ->suffix(fn($record) => ' ' . ($record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('difference_qty')
                    ->label('Selisih')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '0';
                        }
                        $value = (float) $state;
                        if ($value == floor($value)) {
                            return (string) (int) $value;
                        }
                        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
                    })
                    ->sortable()
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->suffix(fn($record) => ' ' . ($record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Biaya Satuan')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Biaya')
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
                return $query->with(['inventoryItem.uom'])
                            ->orderBy('created_at', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function canCreate(): bool
    {
        $adjustment = $this->getOwnerRecord();
        return !$adjustment || !in_array($adjustment->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED]);
    }

    public function canEdit($record): bool
    {
        $adjustment = $this->getOwnerRecord();
        return !$adjustment || !in_array($adjustment->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED]);
    }

    public function canDelete($record): bool
    {
        $adjustment = $this->getOwnerRecord();
        return !$adjustment || !in_array($adjustment->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED]);
    }
}


