<?php

namespace App\Filament\Owner\Resources\Orders\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    protected static ?string $title = 'Refund';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'primary',
                        'processed' => 'success',
                        'completed' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Diminta Oleh')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->wrap()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Diproses Pada')
                    ->dateTime()
                    ->since()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

