<?php

namespace App\Filament\Admin\Resources\Invoices\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Support\Currency;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),

                TextColumn::make('subscription.tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('subscription.plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('success'),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : null),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('Not paid')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                SelectFilter::make('subscription.tenant_id')
                    ->label('Tenant')
                    ->relationship('subscription.tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('due_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('due_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('due_until')
                            ->label('To Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn ($query, $date) => $query->whereDate('due_date', '>=', $date)
                            )
                            ->when(
                                $data['due_until'],
                                fn ($query, $date) => $query->whereDate('due_date', '<=', $date)
                            );
                    }),

                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn ($query) => $query->where('status', 'pending')->where('due_date', '<', now())),

                Filter::make('due_soon')
                    ->label('Due Soon (7 days)')
                    ->query(fn ($query) => $query->where('status', 'pending')->where('due_date', '<=', now()->addDays(7))->where('due_date', '>', now())),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}

