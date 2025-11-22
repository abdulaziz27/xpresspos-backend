<?php

namespace App\Filament\Owner\Resources\PurchaseOrders\RelationManagers;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
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
                        ->required()
                        ->minValue(0.001)
                        ->step(0.001)
                        ->disabled($isReadOnly)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalculate total_cost
                            $unitCost = $get('unit_cost') ?? 0;
                            if ($state && $unitCost) {
                                $set('total_cost', round($state * $unitCost, 2));
                            }
                        })
                        ->helperText('Jumlah yang dipesan'),

                    Forms\Components\TextInput::make('quantity_received')
                        ->label('Qty Diterima')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(0.001)
                        ->disabled($isReadOnly || $isDraft)
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
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalculate total_cost
                            $quantity = $get('quantity_ordered') ?? 0;
                            if ($state && $quantity) {
                                $set('total_cost', round($quantity * $state, 2));
                            }
                        })
                        ->helperText('Biaya per satuan'),
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
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->suffix(fn($record) => ' ' . ($record->uom?->code ?? $record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Qty Diterima')
                    ->numeric(3)
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


