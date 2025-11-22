<?php

namespace App\Filament\Owner\Resources\InventoryItems\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Stok per Store';

    protected static ?string $modelLabel = 'stok';

    protected static ?string $pluralModelLabel = 'stok';

    public function form(Schema $schema): Schema
    {
        // Read-only, no form needed
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('store.name')
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd(),

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
                    ->color(fn($record) => $record->isLowStock() ? 'warning' : ($record->isOutOfStock() ? 'danger' : 'success')),

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
                    ->searchable()
                    ->preload(),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->with('store')
                            ->orderBy('store.name', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50]);
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

