<?php

namespace App\Filament\Owner\Resources\Vouchers\RelationManagers;

use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    protected static ?string $title = 'Riwayat Penukaran';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->url(fn ($record) => route('filament.owner.resources.orders.edit', ['record' => $record->order_id]), true),
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Member')
                    ->placeholder('Umum'),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Diskon')
                    ->money('IDR', true),
                Tables\Columns\TextColumn::make('redeemed_at')
                    ->label('Ditukar Pada')
                    ->dateTime(),
            ])
            ->defaultSort('redeemed_at', 'desc')
            ->actions([])
            ->emptyStateHeading('Belum ada penukaran');
    }
}


