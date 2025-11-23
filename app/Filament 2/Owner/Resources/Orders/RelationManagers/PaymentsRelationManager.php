<?php

namespace App\Filament\Owner\Resources\Orders\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pembayaran';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Referensi')
                    ->toggleable()
                    ->copyable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime()
                    ->since()
                    ->placeholder('-'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

