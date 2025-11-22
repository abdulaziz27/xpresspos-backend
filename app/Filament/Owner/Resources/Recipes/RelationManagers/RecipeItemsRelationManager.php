<?php

namespace App\Filament\Owner\Resources\Recipes\RelationManagers;

use App\Models\InventoryItem;
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
use App\Support\Currency;
use Filament\Forms\Components\Placeholder;

class RecipeItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'inventoryItem.name';

    protected static ?string $title = 'Bahan Resep';

    protected static ?string $modelLabel = 'bahan';

    protected static ?string $pluralModelLabel = 'bahan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('inventory_item_id')
                    ->label('Bahan')
                    ->options(function () {
                        // InventoryItem is tenant-scoped, TenantScope will automatically filter
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
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $inventoryItem = InventoryItem::with('uom')->find($state);
                            if ($inventoryItem) {
                                // Set uom_id from inventory item (enforced by model, but set here for UI consistency)
                                if ($inventoryItem->uom_id) {
                                    $set('uom_id', $inventoryItem->uom_id);
                                }
                                // Set unit_cost from default_cost (ensure it's a float)
                                if ($inventoryItem->default_cost) {
                                    $set('unit_cost', (float) $inventoryItem->default_cost);
                                }
                            }
                        }
                    }),

                Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->minValue(0.001)
                            ->step(0.001)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $unitCost = $get('unit_cost');
                                if ($state && $unitCost) {
                                    // Ensure both values are numeric before calculation
                                    $quantity = is_numeric($state) ? (float) $state : 0;
                                    $cost = is_numeric($unitCost) ? (float) $unitCost : 0;
                                    if ($quantity > 0 && $cost > 0) {
                                        $set('total_cost', round($quantity * $cost, 2));
                                    }
                                }
                            }),

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
                    ]),

                // Hidden field for uom_id (set automatically by model)
                Forms\Components\Hidden::make('uom_id')
                    ->dehydrated(true),

                Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Biaya per Satuan')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                            ->helperText('Diambil dari default cost inventory item'),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Biaya')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                            ->helperText('Dihitung otomatis: Jumlah × Biaya per Satuan'),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(2)
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('inventoryItem.name')
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Bahan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
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
                    ->suffix(fn($record) => ' ' . ($record->uom?->code ?? $record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Biaya per Satuan')
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ditambahkan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Bahan'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('created_at', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

