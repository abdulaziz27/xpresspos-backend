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
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->limit(40)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 40 ? $state : null;
                    }),

                TextColumn::make('category')
                    ->label('Category')
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
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('IDR')
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('vendor')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No Vendor'),

                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->placeholder('No Receipt'),

                TextColumn::make('expense_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Recorded By')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('cash_session_id')
                    ->label('Cash Session')
                    ->badge()
                    ->color('primary')
                    ->placeholder('No Session'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'office_supplies' => 'Office Supplies',
                        'utilities' => 'Utilities',
                        'rent' => 'Rent',
                        'marketing' => 'Marketing',
                        'equipment' => 'Equipment',
                        'maintenance' => 'Maintenance',
                        'travel' => 'Travel',
                        'food' => 'Food & Beverage',
                        'other' => 'Other',
                    ])
                    ->multiple(),

                SelectFilter::make('user_id')
                    ->label('Recorded By')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('expense_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('expense_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('expense_until')
                            ->label('Until Date'),
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
                    ->label('Today')
                    ->query(fn(Builder $query): Builder => $query->whereDate('expense_date', today())),

                Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn(Builder $query): Builder => $query->whereBetween('expense_date', [
                        now()->startOfWeek(),
                        now()->endOfWeek(),
                    ])),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('expense_date', now()->month)),

                Filter::make('has_receipt')
                    ->label('Has Receipt')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('receipt_number')),

                Filter::make('has_vendor')
                    ->label('Has Vendor')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('vendor')),
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
            ->defaultSort('expense_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}
