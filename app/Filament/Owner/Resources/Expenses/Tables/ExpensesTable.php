<?php

namespace App\Filament\Owner\Resources\Expenses\Tables;

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

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(40)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 40 ? $state : null;
                    }),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'office_supplies' => 'gray',
                        'utilities' => 'info',
                        'rent' => 'warning',
                        'marketing' => 'success',
                        'equipment' => 'primary',
                        'maintenance' => 'danger',
                        'travel' => 'info',
                        'food' => 'success',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'office_supplies' => 'Perlengkapan Kantor',
                        'utilities' => 'Utilitas',
                        'rent' => 'Sewa',
                        'marketing' => 'Pemasaran',
                        'equipment' => 'Peralatan',
                        'maintenance' => 'Pemeliharaan',
                        'travel' => 'Perjalanan',
                        'food' => 'Makanan & Minuman',
                        'other' => 'Lainnya',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('vendor')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Tidak Ada Vendor'),

                TextColumn::make('receipt_number')
                    ->label('No. Kwitansi')
                    ->searchable()
                    ->placeholder('Tidak Ada Kwitansi'),

                TextColumn::make('expense_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Dicatat Oleh')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('cash_session_id')
                    ->label('Sesi Kas')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Tidak Ada Sesi'),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'office_supplies' => 'Perlengkapan Kantor',
                        'utilities' => 'Utilitas',
                        'rent' => 'Sewa',
                        'marketing' => 'Pemasaran',
                        'equipment' => 'Peralatan',
                        'maintenance' => 'Pemeliharaan',
                        'travel' => 'Perjalanan',
                        'food' => 'Makanan & Minuman',
                        'other' => 'Lainnya',
                    ])
                    ->multiple(),

                SelectFilter::make('user_id')
                    ->label('Dicatat Oleh')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('expense_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('expense_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('expense_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expense_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['expense_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),

                Filter::make('today')
                    ->label('Hari Ini')
                    ->query(fn(Builder $query): Builder => $query->whereDate('expense_date', today())),

                Filter::make('this_week')
                    ->label('Minggu Ini')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('expense_date', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Filter::make('this_month')
                    ->label('Bulan Ini')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('expense_date', now()->month)),

                Filter::make('has_receipt')
                    ->label('Ada Kwitansi')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('receipt_number')),

                Filter::make('has_vendor')
                    ->label('Ada Vendor')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('vendor')),
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
            ->defaultSort('expense_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
