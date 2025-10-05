<?php

namespace App\Filament\Owner\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Orders';

    public function table(Table $table): Table
    {
        $storeId = auth()->user()->store_id;

        return $table
            ->query(
                Order::query()
                    ->where('store_id', $storeId)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'open' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('member.name')
                    ->label('Customer')
                    ->placeholder('Walk-in Customer'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->paginated(false);
    }
}
