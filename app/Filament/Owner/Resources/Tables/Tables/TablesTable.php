<?php

namespace App\Filament\Owner\Resources\Tables\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table as FilamentTable;

class TablesTable
{
    public static function configure(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                TextColumn::make('table_number')
                    ->label('No. Meja')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Nama Meja')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('store.name')
                    ->label('Cabang')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->numeric()
                    ->alignCenter()
                    ->suffix(' orang')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'available' => 'success',
                        'occupied' => 'warning',
                        'reserved' => 'info',
                        'maintenance' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->options(fn () => static::storeOptions()),
            ])
            ->actions([
                EditAction::make()->label('Ubah'),
                \Filament\Actions\DeleteAction::make()->label('Hapus'),
            ])
            ->defaultSort('table_number')
            ->paginated([10, 25, 50, 100]);
    }

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $user->stores()
            ->select(['stores.id', 'stores.name'])
            ->orderBy('stores.name')
            ->pluck('stores.name', 'stores.id')
            ->toArray();
    }
}
