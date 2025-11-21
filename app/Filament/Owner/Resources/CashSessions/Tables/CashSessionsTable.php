<?php

namespace App\Filament\Owner\Resources\CashSessions\Tables;

use App\Services\GlobalFilterService;
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

class CashSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID Sesi')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('user.name')
                    ->label('Kasir')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('store.name')
                    ->label('Cabang')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'open' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'open' => 'Buka',
                        'closed' => 'Tutup',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('opening_balance')
                    ->label('Saldo Awal')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('closing_balance')
                    ->label('Saldo Akhir')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Belum Ditutup'),

                TextColumn::make('expected_balance')
                    ->label('Saldo Ekspektasi')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Belum Dihitung'),

                TextColumn::make('variance')
                    ->label('Selisih')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record) => $record->hasVariance() ? 'danger' : 'success')
                    ->placeholder('Tidak Ada Selisih'),

                TextColumn::make('cash_sales')
                    ->label('Penjualan Tunai')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Belum Dihitung'),

                TextColumn::make('opened_at')
                    ->label('Dibuka Pada')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('closed_at')
                    ->label('Ditutup Pada')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Masih Terbuka'),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Cabang Toko')
                    ->placeholder('Semua Cabang')
                    ->options(fn () => self::getStoreFilterOptions())
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Buka',
                        'closed' => 'Tutup',
                    ])
                    ->multiple(),

                SelectFilter::make('user_id')
                    ->label('Kasir')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('has_variance')
                    ->label('Ada Selisih')
                    ->query(fn(Builder $query): Builder => $query->whereRaw('ABS(variance) > 0.01')),

                Filter::make('opened_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('opened_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('opened_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['opened_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('opened_at', '>=', $date),
                            )
                            ->when(
                                $data['opened_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('opened_at', '<=', $date),
                            );
                    }),

                Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn(Builder $query): Builder => $query->whereDate('opened_at', today())),

                Filter::make('this_week')
                    ->label('Minggu Ini')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('opened_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('opened_at', now()->month)),
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
            ->defaultSort('opened_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    protected static function getStoreFilterOptions(): array
    {
        /** @var GlobalFilterService $service */
        $service = app(GlobalFilterService::class);

        return $service->getAvailableStores()
            ->pluck('name', 'id')
            ->toArray();
    }
}
