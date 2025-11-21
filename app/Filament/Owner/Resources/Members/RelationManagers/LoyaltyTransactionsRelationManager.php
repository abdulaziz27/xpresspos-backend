<?php

namespace App\Filament\Owner\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'loyaltyTransactions';

    protected static ?string $title = 'Riwayat Poin';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('points')
                    ->label('Poin')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Cabang')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('-')
                    ->url(fn ($record) => $record->order_id ? route('filament.owner.resources.orders.edit', ['record' => $record->order_id]) : null, true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->actions([]);
    }
}


