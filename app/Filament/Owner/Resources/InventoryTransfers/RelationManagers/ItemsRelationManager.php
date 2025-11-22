<?php

namespace App\Filament\Owner\Resources\InventoryTransfers\RelationManagers;

use App\Models\InventoryTransfer;
use App\Models\InventoryItem;
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

    protected static ?string $title = 'Detail Transfer';

    public function form(Schema $schema): Schema
    {
        $transfer = $this->getOwnerRecord();
        $isDraft = !$transfer || $transfer->status === InventoryTransfer::STATUS_DRAFT;
        $isShipped = $transfer && $transfer->status === InventoryTransfer::STATUS_SHIPPED;
        $isReceived = $transfer && $transfer->status === InventoryTransfer::STATUS_RECEIVED;
        $isCancelled = $transfer && $transfer->status === InventoryTransfer::STATUS_CANCELLED;
        $isReadOnly = $isReceived || $isCancelled;

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
                ->disabled($isReadOnly || $isShipped)
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
                    Forms\Components\TextInput::make('quantity_shipped')
                        ->label('Qty Dikirim')
                        ->numeric()
                        ->required()
                        ->minValue(0.001)
                        ->step(0.001)
                        ->disabled($isReadOnly || $isShipped)
                        ->helperText('Jumlah yang dikirim dari toko asal'),

                    Forms\Components\TextInput::make('quantity_received')
                        ->label('Qty Diterima')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->step(0.001)
                        ->disabled($isReadOnly)
                        ->helperText(function ($record, callable $get) use ($isShipped) {
                            return $isShipped 
                                ? 'Jumlah yang diterima di toko tujuan (boleh <= Qty Dikirim)'
                                : 'Jumlah yang diterima di toko tujuan';
                        })
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $shipped = $get('quantity_shipped') ?? 0;
                                    if ($value > $shipped) {
                                        $fail('Qty Diterima tidak boleh lebih besar dari Qty Dikirim.');
                                    }
                                };
                            },
                        ]),
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
                        ->disabled($isReadOnly)
                        ->helperText('Biaya per satuan (opsional)'),
                ]),

            // Hidden field for uom_id (set automatically by model)
            Forms\Components\Hidden::make('uom_id')
                ->dehydrated(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $transfer = $this->getOwnerRecord();
        $isReceived = $transfer && $transfer->status === InventoryTransfer::STATUS_RECEIVED;
        $isCancelled = $transfer && $transfer->status === InventoryTransfer::STATUS_CANCELLED;
        $isShipped = $transfer && $transfer->status === InventoryTransfer::STATUS_SHIPPED;
        $isReadOnly = $isReceived || $isCancelled;

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

                Tables\Columns\TextColumn::make('quantity_shipped')
                    ->label('Qty Dikirim')
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
                        if (!$record->quantity_shipped || $record->quantity_shipped == 0) {
                            return null;
                        }
                        $received = $record->quantity_received ?? 0;
                        $shipped = $record->quantity_shipped;
                        if ($received > $shipped) {
                            return 'danger';
                        }
                        if ($received < $shipped) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->suffix(fn($record) => ' ' . ($record->uom?->code ?? $record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Biaya Satuan')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Item')
                    ->visible(!$isReadOnly && !$isShipped),
            ])
            ->actions([
                EditAction::make()
                    ->visible(!$isReadOnly),
                DeleteAction::make()
                    ->visible(!$isReadOnly && !$isShipped),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(!$isReadOnly && !$isShipped),
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
        $transfer = $this->getOwnerRecord();
        return !$transfer || !in_array($transfer->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED, InventoryTransfer::STATUS_SHIPPED]);
    }

    public function canEdit($record): bool
    {
        $transfer = $this->getOwnerRecord();
        return !$transfer || !in_array($transfer->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED]);
    }

    public function canDelete($record): bool
    {
        $transfer = $this->getOwnerRecord();
        return !$transfer || !in_array($transfer->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED, InventoryTransfer::STATUS_SHIPPED]);
    }
}


