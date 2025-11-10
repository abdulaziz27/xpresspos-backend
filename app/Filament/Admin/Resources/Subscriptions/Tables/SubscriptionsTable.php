<?php

namespace App\Filament\Admin\Resources\Subscriptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Currency;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Subscription ID')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('store.name')
                    ->label('Store')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color(fn($record) => match ($record->plan->name) {
                        'Basic' => 'gray',
                        'Pro' => 'info',
                        'Enterprise' => 'warning',
                        default => 'primary',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'cancelled' => 'danger',
                        'expired' => 'warning',
                        'trial' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->getStateUsing(fn($record) => $record->amount ?? 0)
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium')
                    ->placeholder('Rp 0'),

                TextColumn::make('starts_at')
                    ->label('Starts At')
                    ->date()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->date()
                    ->sortable()
                    ->color(fn($record) => $record->hasExpired() ? 'danger' : 'success'),

                TextColumn::make('trial_ends_at')
                    ->label('Trial Ends At')
                    ->date()
                    ->sortable()
                    ->placeholder('No Trial')
                    ->color(fn($record) => $record->onTrial() ? 'warning' : 'gray'),

                TextColumn::make('days_until_expiration')
                    ->label('Days Left')
                    ->getStateUsing(fn($record) => $record->daysUntilExpiration())
                    ->numeric()
                    ->sortable()
                    ->color(fn($record) => match (true) {
                        $record->daysUntilExpiration() < 0 => 'danger',
                        $record->daysUntilExpiration() <= 7 => 'warning',
                        default => 'success',
                    })
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                        'trial' => 'Trial',
                    ])
                    ->multiple(),

                SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->multiple(),

                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('expiring_soon')
                    ->label('Expiring Soon (7 days)')
                    ->query(fn(Builder $query): Builder => $query->expiringSoon()),

                Filter::make('expired')
                    ->label('Expired')
                    ->query(fn(Builder $query): Builder => $query->expired()),

                Filter::make('on_trial')
                    ->label('On Trial')
                    ->query(fn(Builder $query): Builder => $query->where('trial_ends_at', '>', now())),

                Filter::make('ends_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('ends_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('ends_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['ends_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('ends_at', '>=', $date),
                            )
                            ->when(
                                $data['ends_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('ends_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
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
