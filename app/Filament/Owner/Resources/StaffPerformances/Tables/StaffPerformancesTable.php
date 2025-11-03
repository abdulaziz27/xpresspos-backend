<?php

namespace App\Filament\Owner\Resources\StaffPerformances\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Currency;

class StaffPerformancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff Member')
                    ->searchable()
                    ->sortable()
                    ->url(fn($record) => $record->user ? route('filament.owner.resources.store-user-assignments.edit', $record->user->storeAssignments->first()) : null)
                    ->color('primary'),

                TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('orders_processed')
                    ->label('Orders')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('total_sales')
                    ->label('Total Sales')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->total_sales ?? 0)))
                    ->sortable(),

                TextColumn::make('average_order_value')
                    ->label('Avg Order Value')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->average_order_value ?? 0)))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hours_worked')
                    ->label('Hours Worked')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix(' hrs'),

                TextColumn::make('sales_per_hour')
                    ->label('Sales/Hour')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->sales_per_hour ?? 0)))
                    ->sortable()
                    ->color(function ($state) {
                        if ($state >= 500000) return 'success';
                        if ($state >= 300000) return 'warning';
                        return 'danger';
                    }),

                TextColumn::make('refunds_processed')
                    ->label('Refunds')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('refund_amount')
                    ->label('Refund Amount')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->refund_amount ?? 0)))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer_interactions')
                    ->label('Customers')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('customer_satisfaction_score')
                    ->label('Satisfaction')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix('/5.0')
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        if ($state >= 4.5) return 'success';
                        if ($state >= 4.0) return 'warning';
                        return 'danger';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('efficiency_score')
                    ->label('Efficiency')
                    ->formatStateUsing(function ($record) {
                        if (!$record->hours_worked || !$record->orders_processed) return 'N/A';
                        
                        $ordersPerHour = $record->orders_processed / $record->hours_worked;
                        $score = min(100, ($ordersPerHour / 10) * 100); // Assuming 10 orders/hour is 100%
                        
                        return number_format($score, 1) . '%';
                    })
                    ->color(function ($record) {
                        if (!$record->hours_worked || !$record->orders_processed) return 'gray';
                        
                        $ordersPerHour = $record->orders_processed / $record->hours_worked;
                        $score = min(100, ($ordersPerHour / 10) * 100);
                        
                        if ($score >= 80) return 'success';
                        if ($score >= 60) return 'warning';
                        return 'danger';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Staff Member')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('date_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('date_until')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Filter::make('sales_range')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('sales_from')
                            ->label('Sales From')
                            ->numeric()
                            ->prefix('Rp'),
                        \Filament\Forms\Components\TextInput::make('sales_to')
                            ->label('Sales To')
                            ->numeric()
                            ->prefix('Rp'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['sales_from'],
                                fn (Builder $query, $amount): Builder => $query->where('total_sales', '>=', $amount),
                            )
                            ->when(
                                $data['sales_to'],
                                fn (Builder $query, $amount): Builder => $query->where('total_sales', '<=', $amount),
                            );
                    }),

                Filter::make('performance_level')
                    ->schema([
                        \Filament\Forms\Components\Select::make('level')
                            ->label('Performance Level')
                            ->options([
                                'high' => 'High Performers (Sales/Hour >= 500k)',
                                'medium' => 'Medium Performers (Sales/Hour 300k-500k)',
                                'low' => 'Low Performers (Sales/Hour < 300k)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['level']) return $query;

                        return match($data['level']) {
                            'high' => $query->where('sales_per_hour', '>=', 500000),
                            'medium' => $query->whereBetween('sales_per_hour', [300000, 499999]),
                            'low' => $query->where('sales_per_hour', '<', 300000),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}