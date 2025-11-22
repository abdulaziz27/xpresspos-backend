<?php

namespace App\Filament\Owner\Resources\InventoryItems\RelationManagers;

use App\Models\InventoryLot;
use App\Support\Currency;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LotsRelationManager extends RelationManager
{
    protected static string $relationship = 'lots';

    protected static ?string $title = 'Lot Stok';

    public function form(Schema $schema): Schema
    {
        // Read-only, no form needed
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('lot_code')
            ->columns([
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('lot_code')
                    ->label('Kode Lot')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('initial_qty')
                    ->label('Qty Awal')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('remaining_qty')
                    ->label('Qty Sisa')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record) => $record->remaining_qty <= 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('exp_date')
                    ->label('Tanggal Kadaluarsa')
                    ->date()
                    ->sortable()
                    ->color(fn($record) => $record->exp_date && $record->exp_date->isPast() ? 'danger' : ($record->exp_date && $record->exp_date->isToday() ? 'warning' : null)),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'active' => 'success',
                        'expired' => 'warning',
                        'depleted' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'active' => 'Aktif',
                        'expired' => 'Kadaluarsa',
                        'depleted' => 'Habis',
                        default => $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'expired' => 'Kadaluarsa',
                        'depleted' => 'Habis',
                    ]),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('exp_date', 'asc')
                            ->orderBy('lot_code', 'asc');
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


