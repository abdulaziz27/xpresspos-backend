<?php

namespace App\Filament\Owner\Resources\TableOccupancyHistories\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;

class TableOccupancyHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('table.name')
                    ->label('Table')
                    ->searchable()
                    ->sortable()
                    ->url(fn($record) => $record->table ? route('filament.owner.resources.tables.edit', $record->table) : null)
                    ->color('primary'),

                TextColumn::make('table.table_number')
                    ->label('Table #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->placeholder('No order')
                    ->url(fn($record) => $record->order ? route('filament.owner.resources.orders.edit', $record->order) : null)
                    ->color('primary'),

                TextColumn::make('party_size')
                    ->label('Party Size')
                    ->numeric()
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('occupied_at')
                    ->label('Occupied At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('cleared_at')
                    ->label('Cleared At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Still occupied')
                    ->color(fn($state) => $state ? 'success' : 'warning'),

                TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(function ($record) {
                        if (!$record->duration_minutes) {
                            if ($record->status === 'occupied') {
                                $minutes = $record->occupied_at->diffInMinutes(now());
                                $hours = intval($minutes / 60);
                                $mins = $minutes % 60;
                                $formatted = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
                                return $formatted . ' (ongoing)';
                            }
                            return 'N/A';
                        }
                        return $record->getFormattedDuration();
                    })
                    ->sortable()
                    ->color(function ($record) {
                        if (!$record->duration_minutes && $record->status === 'occupied') {
                            $minutes = $record->occupied_at->diffInMinutes(now());
                            if ($minutes > 180) return 'danger'; // Over 3 hours
                            if ($minutes > 120) return 'warning'; // Over 2 hours
                        }
                        return 'gray';
                    }),

                TextColumn::make('order_total')
                    ->label('Order Total')
                    ->formatStateUsing(fn($s) => Currency::rupiah((float) $s))
                    ->sortable()
                    ->placeholder('No order'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'occupied' => 'warning',
                        'cleared' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state)),

                TextColumn::make('user.name')
                    ->label('Served By')
                    ->searchable()
                    ->placeholder('System'),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->notes)
                    ->placeholder('No notes')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'occupied' => 'Currently Occupied',
                        'cleared' => 'Cleared',
                    ]),

                SelectFilter::make('table_id')
                    ->label('Table')
                    ->relationship('table', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('party_size_range')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('party_size_from')
                            ->label('Party Size From')
                            ->numeric()
                            ->minValue(1),
                        \Filament\Forms\Components\TextInput::make('party_size_to')
                            ->label('Party Size To')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['party_size_from'],
                                fn (Builder $query, $size): Builder => $query->where('party_size', '>=', $size),
                            )
                            ->when(
                                $data['party_size_to'],
                                fn (Builder $query, $size): Builder => $query->where('party_size', '<=', $size),
                            );
                    }),

                Filter::make('duration_range')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('duration_from')
                            ->label('Duration From (minutes)')
                            ->numeric()
                            ->minValue(0),
                        \Filament\Forms\Components\TextInput::make('duration_to')
                            ->label('Duration To (minutes)')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['duration_from'],
                                fn (Builder $query, $duration): Builder => $query->where('duration_minutes', '>=', $duration),
                            )
                            ->when(
                                $data['duration_to'],
                                fn (Builder $query, $duration): Builder => $query->where('duration_minutes', '<=', $duration),
                            );
                    }),

                Filter::make('date_range')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('occupied_from')
                            ->label('Occupied From'),
                        \Filament\Forms\Components\DatePicker::make('occupied_until')
                            ->label('Occupied Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['occupied_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occupied_at', '>=', $date),
                            )
                            ->when(
                                $data['occupied_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('occupied_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('occupied_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}