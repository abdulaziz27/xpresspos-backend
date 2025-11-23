<?php

namespace App\Filament\Owner\Resources\Orders\Tables;

use App\Services\GlobalFilterService;
use Illuminate\Support\Carbon;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Currency;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Order')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'open' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('store.name')
                    ->label('Cabang')
                    ->badge()
                    ->weight('medium')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('member.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Pelanggan Umum'),

                TextColumn::make('table.name')
                    ->label('Meja')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Tanpa Meja'),

                TextColumn::make('user.name')
                    ->label('Staf')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('total_items')
                    ->label('Item')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('payment_mode')
                    ->label('Mode Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'open_bill' => 'Open Bill',
                        'direct' => 'Langsung',
                        default => 'Tidak Diketahui',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'open_bill' => 'warning',
                        'direct' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('Tidak Diketahui'),

                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge()
                    ->color('success')
                    ->placeholder('Belum Diatur'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Belum Selesai'),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('Cabang Toko')
                    ->placeholder('Semua Cabang')
                    ->options(fn () => self::getStoreFilterOptions())
                    ->searchable(),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Terbuka',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Tunai',
                        'card' => 'Kartu',
                        'qris' => 'QRIS',
                        'transfer' => 'Transfer Bank',
                        'other' => 'Lainnya',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_mode')
                    ->label('Mode Pembayaran')
                    ->options([
                        'direct' => 'Langsung',
                        'open_bill' => 'Open Bill',
                    ])
                    ->multiple(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('created_until')
                            ->label('Hingga Tanggal'),
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
                    })
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['created_from'] ?? null) && filled($data['created_until'] ?? null) => sprintf(
                            'Tanggal dibuat: %s - %s',
                            Carbon::parse($data['created_from'])->format('d M'),
                            Carbon::parse($data['created_until'])->format('d M'),
                        ),
                        filled($data['created_from'] ?? null) => 'Mulai ' . Carbon::parse($data['created_from'])->format('d M'),
                        filled($data['created_until'] ?? null) => 'Sampai ' . Carbon::parse($data['created_until'])->format('d M'),
                        default => null,
                    }),

                Filter::make('completed_at')
                    ->form([
                        DatePicker::make('completed_from')
                            ->label('Selesai Dari'),
                        DatePicker::make('completed_until')
                            ->label('Selesai Hingga'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['completed_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('completed_at', '>=', $date),
                            )
                            ->when(
                                $data['completed_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('completed_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(fn (array $data): ?string => match (true) {
                        filled($data['completed_from'] ?? null) && filled($data['completed_until'] ?? null) => sprintf(
                            'Tgl selesai: %s - %s',
                            Carbon::parse($data['completed_from'])->format('d M'),
                            Carbon::parse($data['completed_until'])->format('d M'),
                        ),
                        filled($data['completed_from'] ?? null) => 'Selesai ≥ ' . Carbon::parse($data['completed_from'])->format('d M'),
                        filled($data['completed_until'] ?? null) => 'Selesai ≤ ' . Carbon::parse($data['completed_until'])->format('d M'),
                        default => null,
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
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
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
