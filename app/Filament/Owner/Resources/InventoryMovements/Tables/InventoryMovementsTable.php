<?php

namespace App\Filament\Owner\Resources\InventoryMovements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'sale' => 'success',
                        'purchase' => 'info',
                        'adjustment_in' => 'warning',
                        'adjustment_out' => 'danger',
                        'transfer_in' => 'primary',
                        'transfer_out' => 'gray',
                        'return' => 'success',
                        'waste' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'sale' => 'Penjualan',
                        'purchase' => 'Pembelian',
                        'adjustment_in' => 'Penyesuaian Masuk',
                        'adjustment_out' => 'Penyesuaian Keluar',
                        'transfer_in' => 'Transfer Masuk',
                        'transfer_out' => 'Transfer Keluar',
                        'return' => 'Retur',
                        'waste' => 'Sisa/Buang',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn($record) => $record->isStockIncrease() ? 'success' : 'danger'),

                TextColumn::make('unit_cost')
                    ->label('Biaya per Unit')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('total_cost')
                    ->label('Total Biaya')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('reason')
                    ->label('Alasan')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('user.name')
                    ->label('Dicatat Oleh')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Pergerakan')
                    ->options([
                        'sale' => 'Penjualan',
                        'purchase' => 'Pembelian',
                        'adjustment_in' => 'Penyesuaian Masuk',
                        'adjustment_out' => 'Penyesuaian Keluar',
                        'transfer_in' => 'Transfer Masuk',
                        'transfer_out' => 'Transfer Keluar',
                        'return' => 'Retur',
                        'waste' => 'Sisa/Buang',
                    ])
                    ->multiple(),

                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('stock_in')
                    ->label('Pergerakan Stok Masuk')
                    ->query(fn(Builder $query): Builder => $query->stockIn()),

                Filter::make('stock_out')
                    ->label('Pergerakan Stok Keluar')
                    ->query(fn(Builder $query): Builder => $query->stockOut()),

                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

                Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn(Builder $query): Builder => $query->whereDate('created_at', today())),

                Filter::make('this_week')
                    ->label('Minggu Ini')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('created_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('created_at', now()->month)),
            ])
            ->actions([
                ViewAction::make()->label('Lihat'),
                EditAction::make()->label('Ubah'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
