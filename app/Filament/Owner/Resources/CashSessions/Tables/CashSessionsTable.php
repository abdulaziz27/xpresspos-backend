<?php

namespace App\Filament\Owner\Resources\CashSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CashSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Session ID')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('user.name')
                    ->label('Cashier')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'open' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('opening_balance')
                    ->label('Opening Balance')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('closing_balance')
                    ->label('Closing Balance')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Not Closed'),

                TextColumn::make('expected_balance')
                    ->label('Expected Balance')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Not Calculated'),

                TextColumn::make('variance')
                    ->label('Variance')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd()
                    ->color(fn($record) => $record->hasVariance() ? 'danger' : 'success')
                    ->placeholder('No Variance'),

                TextColumn::make('cash_sales')
                    ->label('Cash Sales')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('Not Calculated'),

                TextColumn::make('opened_at')
                    ->label('Opened At')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('closed_at')
                    ->label('Closed At')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Still Open'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ])
                    ->multiple(),

                SelectFilter::make('user_id')
                    ->label('Cashier')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('has_variance')
                    ->label('Has Variance')
                    ->query(fn(Builder $query): Builder => $query->whereRaw('ABS(variance) > 0.01')),

                Filter::make('opened_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('opened_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('opened_until')
                            ->label('Until Date'),
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
                    ->label('Today')
                    ->query(fn(Builder $query): Builder => $query->whereDate('opened_at', today())),

                Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('opened_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('opened_at', now()->month)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('opened_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
