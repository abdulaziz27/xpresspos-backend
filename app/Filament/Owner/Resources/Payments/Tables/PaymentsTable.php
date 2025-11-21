<?php

namespace App\Filament\Owner\Resources\Payments\Tables;

use App\Services\StoreContext;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID Pembayaran')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'cash' => 'Tunai',
                        'credit_card' => 'Kartu Kredit',
                        'debit_card' => 'Kartu Debit',
                        'qris' => 'QRIS',
                        'bank_transfer' => 'Transfer Bank',
                        'e_wallet' => 'E-Wallet',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'cash' => 'success',
                        'credit_card' => 'info',
                        'debit_card' => 'info',
                        'qris' => 'warning',
                        'bank_transfer' => 'primary',
                        'e_wallet' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'pending' => 'Menunggu',
                        'processing' => 'Diproses',
                        'completed' => 'Berhasil',
                        'failed' => 'Gagal',
                        'cancelled' => 'Dibatalkan',
                        'refunded' => 'Dikembalikan',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'refunded' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->color('info')
                    ->placeholder('Langsung'),

                TextColumn::make('gateway_fee')
                    ->label('Biaya Gateway')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Tanpa Biaya'),

                TextColumn::make('reference_number')
                    ->label('Referensi')
                    ->searchable()
                    ->limit(20)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 20 ? $state : null;
                    }),

                TextColumn::make('processed_at')
                    ->label('Diproses Pada')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Belum Diproses'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Menunggu',
                        'processing' => 'Diproses',
                        'completed' => 'Berhasil',
                        'failed' => 'Gagal',
                        'cancelled' => 'Dibatalkan',
                        'refunded' => 'Dikembalikan',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Tunai',
                        'credit_card' => 'Kartu Kredit',
                        'debit_card' => 'Kartu Debit',
                        'qris' => 'QRIS',
                        'bank_transfer' => 'Transfer Bank',
                        'e_wallet' => 'E-Wallet',
                    ])
                    ->multiple(),

                SelectFilter::make('gateway')
                    ->options(function () {
                        return \App\Models\Payment::whereNotNull('gateway')
                            ->distinct()
                            ->pluck('gateway', 'gateway');
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->options(self::storeOptions())
                    ->placeholder('Semua cabang'),

                Filter::make('has_gateway_fee')
                    ->label('Memiliki Biaya Gateway')
                    ->query(fn(Builder $query): Builder => $query->where('gateway_fee', '>', 0)),

                Filter::make('processed_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('processed_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('processed_until')
                            ->label('Hingga Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['processed_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('processed_at', '>=', $date),
                            )
                            ->when(
                                $data['processed_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('processed_at', '<=', $date),
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
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
}
