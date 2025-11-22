<?php

namespace App\Filament\Owner\Resources\Orders\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderDiscountsRelationManager extends RelationManager
{
    protected static string $relationship = 'discounts';

    protected static ?string $title = 'Diskon Pesanan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PROMOTION' => 'primary',
                        'VOUCHER' => 'success',
                        'MANUAL' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Nilai Diskon')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('promotion.name')
                    ->label('Promo')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

