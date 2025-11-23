<?php

namespace App\Filament\Owner\Resources\Tables\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TableOccupancyHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'occupancyHistories';

    protected static ?string $title = 'Histori Penggunaan Meja';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occupied_at')
                    ->label('Mulai Digunakan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('cleared_at')
                    ->label('Selesai Digunakan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Masih Digunakan'),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->formatStateUsing(fn (?int $state, $record): string => $record->getFormattedDuration())
                    ->sortable(),

                Tables\Columns\TextColumn::make('party_size')
                    ->label('Jumlah Orang')
                    ->numeric()
                    ->alignCenter()
                    ->suffix(' orang')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_total')
                    ->label('Total Order')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) ($state ?? 0)))
                    ->alignEnd()
                    ->weight('medium')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'occupied' => 'warning',
                        'cleared' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('No. Order')
                    ->badge()
                    ->color('info')
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Staf')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('occupied_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

