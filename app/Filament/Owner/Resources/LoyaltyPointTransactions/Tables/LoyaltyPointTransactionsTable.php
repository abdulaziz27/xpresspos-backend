<?php

namespace App\Filament\Owner\Resources\LoyaltyPointTransactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyPointTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable()
                    ->url(fn($record) => $record->member ? route('filament.owner.resources.members.edit', $record->member) : null)
                    ->color('primary'),

                TextColumn::make('member.member_number')
                    ->label('Member #')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'earned' => 'success',
                        'redeemed' => 'warning',
                        'adjusted' => 'info',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('points')
                    ->label('Points')
                    ->numeric()
                    ->sortable()
                    ->color(fn($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(function ($state) {
                        $prefix = $state > 0 ? '+' : '';
                        return $prefix . number_format($state, 0, ',', '.');
                    }),

                TextColumn::make('balance_before')
                    ->label('Balance Before')
                    ->numeric()
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', '.'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->numeric()
                    ->formatStateUsing(fn($state) => number_format($state, 0, ',', '.')),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->reason),

                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->placeholder('N/A')
                    ->url(fn($record) => $record->order ? route('filament.owner.resources.orders.edit', $record->order) : null)
                    ->color('primary'),

                TextColumn::make('user.name')
                    ->label('Processed By')
                    ->searchable()
                    ->placeholder('System'),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiry')
                    ->color(function ($record) {
                        if (!$record->expires_at) return 'gray';
                        
                        $daysUntilExpiry = now()->diffInDays($record->expires_at, false);
                        if ($daysUntilExpiry < 0) return 'danger';
                        if ($daysUntilExpiry <= 30) return 'warning';
                        return 'success';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'earned' => 'Earned',
                        'redeemed' => 'Redeemed',
                        'adjusted' => 'Adjusted',
                        'expired' => 'Expired',
                    ]),

                SelectFilter::make('member_id')
                    ->label('Member')
                    ->relationship('member', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('points_range')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('points_from')
                            ->label('Points From')
                            ->numeric(),
                        \Filament\Forms\Components\TextInput::make('points_to')
                            ->label('Points To')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['points_from'],
                                fn (Builder $query, $points): Builder => $query->where('points', '>=', $points),
                            )
                            ->when(
                                $data['points_to'],
                                fn (Builder $query, $points): Builder => $query->where('points', '<=', $points),
                            );
                    }),

                Filter::make('date_range')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('created_until')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}