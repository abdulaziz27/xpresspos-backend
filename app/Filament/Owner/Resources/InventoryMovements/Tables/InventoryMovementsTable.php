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
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('type')
                    ->label('Type')
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
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn($record) => $record->isStockIncrease() ? 'success' : 'danger'),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),

                TextColumn::make('user.name')
                    ->label('Recorded By')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'sale' => 'Sale',
                        'purchase' => 'Purchase',
                        'adjustment_in' => 'Adjustment In',
                        'adjustment_out' => 'Adjustment Out',
                        'transfer_in' => 'Transfer In',
                        'transfer_out' => 'Transfer Out',
                        'return' => 'Return',
                        'waste' => 'Waste',
                    ])
                    ->multiple(),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('stock_in')
                    ->label('Stock In Movements')
                    ->query(fn(Builder $query): Builder => $query->stockIn()),

                Filter::make('stock_out')
                    ->label('Stock Out Movements')
                    ->query(fn(Builder $query): Builder => $query->stockOut()),

                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
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
                    ->label('Today')
                    ->query(fn(Builder $query): Builder => $query->whereDate('created_at', today())),

                Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('created_at', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('created_at', now()->month)),
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
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
