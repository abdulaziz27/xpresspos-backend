<?php

namespace App\Filament\Owner\Resources\InventoryItems\RelationManagers;

use App\Models\InventoryMovement;
use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryMovements';

    protected static ?string $title = 'Histori Pergerakan Stok';

    protected static ?string $modelLabel = 'pergerakan';

    protected static ?string $pluralModelLabel = 'pergerakan';

    public function form(Schema $schema): Schema
    {
        // Read-only, no form needed
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn($record) => $record->isStockIncrease() ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => match($state) {
                        InventoryMovement::TYPE_SALE => 'Penjualan',
                        InventoryMovement::TYPE_PURCHASE => 'Pembelian',
                        InventoryMovement::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
                        InventoryMovement::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
                        InventoryMovement::TYPE_TRANSFER_IN => 'Transfer Masuk',
                        InventoryMovement::TYPE_TRANSFER_OUT => 'Transfer Keluar',
                        InventoryMovement::TYPE_RETURN => 'Return',
                        InventoryMovement::TYPE_WASTE => 'Waste',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color(fn($record) => $record->isStockIncrease() ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Referensi')
                    ->formatStateUsing(function ($state, $record) {
                        if ($state && $record->reference_id) {
                            $type = class_basename($state);
                            return $type . ' #' . substr($record->reference_id, 0, 8);
                        }
                        return '-';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->wrap()
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Pergerakan')
                    ->options([
                        InventoryMovement::TYPE_SALE => 'Penjualan',
                        InventoryMovement::TYPE_PURCHASE => 'Pembelian',
                        InventoryMovement::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
                        InventoryMovement::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
                        InventoryMovement::TYPE_TRANSFER_IN => 'Transfer Masuk',
                        InventoryMovement::TYPE_TRANSFER_OUT => 'Transfer Keluar',
                        InventoryMovement::TYPE_RETURN => 'Return',
                        InventoryMovement::TYPE_WASTE => 'Waste',
                    ]),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('created_at', 'desc');
            })
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit($record): bool
    {
        return false;
    }

    public function canDelete($record): bool
    {
        return false;
    }
}

