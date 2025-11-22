<?php

namespace App\Filament\Owner\Resources\StockLevels;

use App\Filament\Owner\Resources\StockLevels\Pages;
use App\Models\StockLevel;
use App\Models\InventoryItem;
use App\Services\StoreContext;
use App\Support\Currency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static ?string $navigationLabel = 'Stok per Toko';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 20; // 2. Stok per Toko

    public static function form(Schema $schema): Schema
    {
        // Read-only resource, form tidak digunakan
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn($record) => $record->inventoryItem?->sku ? 'SKU: ' . $record->inventoryItem->sku : null),

                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->suffix(fn($record) => ' ' . ($record->inventoryItem?->uom?->code ?? '')),

                Tables\Columns\TextColumn::make('reserved_stock')
                    ->label('Stok Dipesan')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Stok Tersedia')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record) => $record->isOutOfStock() ? 'danger' : ($record->isLowStock() ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Min Stok')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('average_cost')
                    ->label('Rata-rata Cost')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total Nilai')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('id')
                    ->label('Status Stok')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->isOutOfStock()) {
                            return 'Habis';
                        }
                        if ($record->isLowStock()) {
                            return 'Low';
                        }
                        return 'Normal';
                    })
                    ->color(function ($record) {
                        if ($record->isOutOfStock()) {
                            return 'danger';
                        }
                        if ($record->isLowStock()) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->sortable(false),

                Tables\Columns\TextColumn::make('last_movement_at')
                    ->label('Pergerakan Terakhir')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->options(self::storeOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('inventory_item_id')
                    ->label('Item Inventori')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(function () {
                        return InventoryItem::query()
                            ->whereNotNull('category')
                            ->distinct()
                            ->pluck('category', 'category');
                    })
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('inventoryItem', function (Builder $itemQuery) use ($data) {
                                $itemQuery->where('category', $data['value']);
                            });
                        }
                    })
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_low_stock')
                    ->label('Stok Rendah')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya stok rendah')
                    ->falseLabel('Tanpa stok rendah')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('inventoryItem', function (Builder $itemQuery) {
                            $itemQuery->where('track_stock', true);
                        })->whereColumn('stock_levels.available_stock', '<=', 'stock_levels.min_stock_level'),
                        false: fn (Builder $query) => $query->where(function (Builder $inner) {
                            $inner->whereDoesntHave('inventoryItem', function (Builder $itemQuery) {
                                $itemQuery->where('track_stock', true);
                            })
                            ->orWhereColumn('stock_levels.available_stock', '>', 'stock_levels.min_stock_level');
                        })
                    ),
            ])
            ->searchable()
            ->modifyQueryUsing(function (Builder $query) {
                // Default sorting using joined tables (joins already done in getEloquentQuery)
                $query->orderBy('stores.name', 'asc')
                      ->orderBy('inventory_items.name', 'asc')
                      ->orderBy('stock_levels.id', 'asc');
                
                // Custom search for inventoryItem.name and sku
                if (request()->filled('tableSearch')) {
                    $search = request()->input('tableSearch');
                    $query->where(function (Builder $q) use ($search) {
                        $q->where('inventory_items.name', 'like', "%{$search}%")
                          ->orWhere('inventory_items.sku', 'like', "%{$search}%");
                    });
                }
            })
            ->actions([])
            ->bulkActions([])
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return StoreContext::instance()
            ->accessibleStores($user)
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder $query */
        $query = StockLevel::query()->forAllStores()->with(['inventoryItem.uom', 'store']);
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin_sistem')) {
            return $query;
        }

        $storeIds = StoreContext::instance()
            ->accessibleStores($user)
            ->pluck('id');

        if ($storeIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        // Join stores and inventory_items for sorting (will be used in modifyQueryUsing)
        $query->leftJoin('stores', 'stock_levels.store_id', '=', 'stores.id')
              ->leftJoin('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
              ->select('stock_levels.*');

        return $query->whereIn('stock_levels.store_id', $storeIds);
    }
}


