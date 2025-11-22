<?php

namespace App\Filament\Owner\Resources\Tables\Tables;

use App\Services\GlobalFilterService;
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

                TextColumn::make('location')
                    ->label('Lokasi')
                    ->badge()
                    ->formatStateUsing(function (?string $state): string {
                        if (empty($state)) {
                            return 'Belum Diatur';
                        }
                        
                        return match ($state) {
                            'indoor' => 'Dalam Ruangan',
                            'outdoor' => 'Luar Ruangan',
                            'terrace' => 'Teras',
                            'vip' => 'Area VIP',
                            'bar' => 'Area Bar',
                            'other' => 'Lainnya',
                            default => ucfirst($state), // Fallback untuk nilai custom
                        };
                    })
                    ->color(function (?string $state): string {
                        if (empty($state)) {
                            return 'gray';
                        }
                        
                        return match ($state) {
                            'indoor' => 'info',
                            'outdoor' => 'success',
                            'terrace' => 'warning',
                            'vip' => 'danger',
                            'bar' => 'gray',
                            default => 'gray',
                        };
                    })
                    ->sortable()
                    ->searchable()
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
                        'cleaning' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('currentOrder.order_number')
                    ->label('Order Aktif')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Tidak Ada Order')
                    ->toggleable(),

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
                    ->options(fn () => static::storeOptions())
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Tersedia',
                        'occupied' => 'Terisi',
                        'reserved' => 'Direservasi',
                        'maintenance' => 'Perawatan',
                        'cleaning' => 'Pembersihan',
                    ])
                    ->multiple(),

                TernaryFilter::make('is_active')
                    ->label('Aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif Saja')
                    ->falseLabel('Nonaktif Saja'),

                SelectFilter::make('location')
                    ->label('Lokasi')
                    ->options([
                        'indoor' => 'Dalam Ruangan',
                        'outdoor' => 'Luar Ruangan',
                        'terrace' => 'Teras',
                        'vip' => 'Area VIP',
                        'bar' => 'Area Bar',
                        'other' => 'Lainnya',
                    ])
                    ->multiple()
                    ->searchable(),
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
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);

        return $service->getAvailableStores()
            ->pluck('name', 'id')
            ->toArray();
    }
}
