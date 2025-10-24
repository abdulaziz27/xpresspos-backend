<?php

namespace App\Filament\Owner\Resources\Tables\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table as FilamentTable;

class TablesTable
{
    public static function configure(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                TextColumn::make('table_number')
                    ->label('Table #')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('name')
                    ->label('Table Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->alignCenter()
                    ->suffix(' people')
                    ->sortable(),

                TextColumn::make('location')
                    ->label('Location')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'indoor' => 'gray',
                        'outdoor' => 'success',
                        'terrace' => 'info',
                        'vip' => 'warning',
                        'bar' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'available' => 'success',
                        'occupied' => 'warning',
                        'reserved' => 'info',
                        'maintenance' => 'danger',
                        'cleaning' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total_occupancy_count')
                    ->label('Total Occupancies')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('average_occupancy_duration')
                    ->label('Avg Duration')
                    ->numeric(decimalPlaces: 1)
                    ->alignCenter()
                    ->sortable()
                    ->suffix(' min')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('occupied_at')
                    ->label('Occupied Since')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Not Occupied')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'occupied' => 'Occupied',
                        'reserved' => 'Reserved',
                        'maintenance' => 'Maintenance',
                        'cleaning' => 'Cleaning',
                    ])
                    ->multiple(),

                SelectFilter::make('location')
                    ->options([
                        'indoor' => 'Indoor',
                        'outdoor' => 'Outdoor',
                        'terrace' => 'Terrace',
                        'vip' => 'VIP Section',
                        'bar' => 'Bar Area',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All tables')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('is_occupied')
                    ->label('Occupancy')
                    ->placeholder('All tables')
                    ->trueLabel('Occupied only')
                    ->falseLabel('Available only')
                    ->query(fn($query) => $query->occupied()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('table_number')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
