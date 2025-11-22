<?php

namespace App\Filament\Owner\Resources\StockLevels;

use App\Filament\Owner\Resources\StockLevels\Pages;
use App\Models\StockLevel;
use App\Services\StoreContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static ?string $navigationLabel = 'Level Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 15;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item Inventori')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->description(fn($record) => $record->inventoryItem?->uom?->name ?? ''),
                Tables\Columns\TextColumn::make('reserved_stock')
                    ->label('Stok Terpesan')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Stok Tersedia')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Min Stok')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('average_cost')
                    ->label('Biaya Rata-rata')
                    ->money('IDR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Nilai Stok')
                    ->money('IDR', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_movement_at')
                    ->label('Pergerakan Terakhir')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->relationship('store', 'name')
                    ->options(self::storeOptions())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('inventory_item_id')
                    ->label('Item Inventori')
                    ->relationship('inventoryItem', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_low_stock')
                    ->label('Stok Rendah')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya stok rendah')
                    ->falseLabel('Tanpa stok rendah')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('inventoryItem', function (Builder $itemQuery) {
                            $itemQuery->where('track_stock', true);
                        })->whereColumn('stock_levels.current_stock', '<=', 'stock_levels.min_stock_level'),
                        false: fn (Builder $query) => $query->where(function (Builder $inner) {
                            $inner->whereDoesntHave('inventoryItem')
                                ->orWhereHas('inventoryItem', function (Builder $itemQuery) {
                                    $itemQuery->where('track_stock', false);
                                })
                                ->orWhereColumn('stock_levels.current_stock', '>', 'stock_levels.min_stock_level');
                        })
                    ),
            ])
            ->actions([])
            ->bulkActions([]);
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

        return $query->whereIn('store_id', $storeIds);
    }
}


