<?php

namespace App\Filament\Owner\Resources\ProductPriceHistories\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Support\Currency;
use Illuminate\Database\Eloquent\Builder;

class ProductPriceHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->url(fn($record) => $record->product ? route('filament.owner.resources.products.edit', $record->product) : null)
                    ->color('primary'),

                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('old_price')
                    ->label('Old Price')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable(),

                TextColumn::make('new_price')
                    ->label('New Price')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable(),

                TextColumn::make('price_change')
                    ->label('Price Change')
                    ->formatStateUsing(function ($record) {
                        $change = $record->new_price - $record->old_price;
                        $percentage = $record->old_price > 0 ? (($change / $record->old_price) * 100) : 0;
                        
                        $prefix = $change > 0 ? '+' : '';
                        $changeFormatted = $prefix . 'Rp ' . number_format(abs($change), 0, ',', '.');
                        $percentageFormatted = $prefix . number_format($percentage, 1) . '%';
                        
                        return $changeFormatted . ' (' . $percentageFormatted . ')';
                    })
                    ->color(function ($record) {
                        $change = $record->new_price - $record->old_price;
                        if ($change > 0) return 'success';
                        if ($change < 0) return 'danger';
                        return 'gray';
                    }),

                TextColumn::make('old_cost_price')
                    ->label('Old Cost')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('new_cost_price')
                    ->label('New Cost')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cost_change')
                    ->label('Cost Change')
                    ->formatStateUsing(function ($record) {
                        if (!$record->old_cost_price || !$record->new_cost_price) {
                            return 'N/A';
                        }
                        
                        $change = $record->new_cost_price - $record->old_cost_price;
                        $percentage = $record->old_cost_price > 0 ? (($change / $record->old_cost_price) * 100) : 0;
                        
                        $prefix = $change > 0 ? '+' : '';
                        $changeFormatted = $prefix . 'Rp ' . number_format(abs($change), 0, ',', '.');
                        $percentageFormatted = $prefix . number_format($percentage, 1) . '%';
                        
                        return $changeFormatted . ' (' . $percentageFormatted . ')';
                    })
                    ->color(function ($record) {
                        if (!$record->old_cost_price || !$record->new_cost_price) {
                            return 'gray';
                        }
                        
                        $change = $record->new_cost_price - $record->old_cost_price;
                        if ($change > 0) return 'danger'; // Cost increase is bad
                        if ($change < 0) return 'success'; // Cost decrease is good
                        return 'gray';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('margin_change')
                    ->label('Margin Impact')
                    ->formatStateUsing(function ($record) {
                        $oldMargin = $record->old_price > 0 && $record->old_cost_price ? 
                            (($record->old_price - $record->old_cost_price) / $record->old_price) * 100 : 0;
                        
                        $newMargin = $record->new_price > 0 && $record->new_cost_price ? 
                            (($record->new_price - $record->new_cost_price) / $record->new_price) * 100 : 0;
                        
                        if (!$record->old_cost_price || !$record->new_cost_price) {
                            return 'N/A';
                        }
                        
                        $marginChange = $newMargin - $oldMargin;
                        $prefix = $marginChange > 0 ? '+' : '';
                        
                        return $prefix . number_format($marginChange, 1) . '%';
                    })
                    ->color(function ($record) {
                        if (!$record->old_cost_price || !$record->new_cost_price) {
                            return 'gray';
                        }
                        
                        $oldMargin = $record->old_price > 0 ? 
                            (($record->old_price - $record->old_cost_price) / $record->old_price) * 100 : 0;
                        
                        $newMargin = $record->new_price > 0 ? 
                            (($record->new_price - $record->new_cost_price) / $record->new_price) * 100 : 0;
                        
                        $marginChange = $newMargin - $oldMargin;
                        if ($marginChange > 0) return 'success';
                        if ($marginChange < 0) return 'danger';
                        return 'gray';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->reason)
                    ->placeholder('No reason provided'),

                TextColumn::make('changedBy.name')
                    ->label('Changed By')
                    ->searchable()
                    ->placeholder('System'),

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('price_change_type')
                    ->schema([
                        \Filament\Forms\Components\Select::make('change_type')
                            ->label('Price Change Type')
                            ->options([
                                'increase' => 'Price Increase',
                                'decrease' => 'Price Decrease',
                                'no_change' => 'No Change',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['change_type']) return $query;

                        return match($data['change_type']) {
                            'increase' => $query->whereRaw('new_price > old_price'),
                            'decrease' => $query->whereRaw('new_price < old_price'),
                            'no_change' => $query->whereRaw('new_price = old_price'),
                            default => $query,
                        };
                    }),

                Filter::make('date_range')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('effective_from')
                            ->label('Effective From'),
                        \Filament\Forms\Components\DatePicker::make('effective_until')
                            ->label('Effective Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['effective_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('effective_date', '>=', $date),
                            )
                            ->when(
                                $data['effective_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('effective_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('changed_by')
                    ->label('Changed By')
                    ->relationship('changedBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('effective_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }
}