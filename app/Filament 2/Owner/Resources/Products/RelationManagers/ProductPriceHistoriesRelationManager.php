<?php

namespace App\Filament\Owner\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Support\Currency;

class ProductPriceHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceHistory';

    protected static ?string $recordTitleAttribute = 'effective_date';

    protected static ?string $title = 'Riwayat Harga';

    protected static ?string $modelLabel = 'riwayat harga';

    protected static ?string $pluralModelLabel = 'riwayat harga';

    public function form(Schema $schema): Schema
    {
        // Read-only: no form for creating/editing
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('effective_date')
            ->columns([
                Tables\Columns\TextColumn::make('old_price')
                    ->label('Harga Lama')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('new_price')
                    ->label('Harga Baru')
                    ->formatStateUsing(fn($state) => Currency::rupiah((float) $state))
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('old_cost_price')
                    ->label('Harga Pokok Lama')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('new_cost_price')
                    ->label('Harga Pokok Baru')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : '-')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan Perubahan')
                    ->wrap()
                    ->placeholder('-')
                    ->limit(50),

                Tables\Columns\TextColumn::make('changedBy.name')
                    ->label('Diubah Oleh')
                    ->placeholder('-')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('effective_date')
                    ->label('Tanggal Efektif')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Read-only: no create action
            ])
            ->actions([
                // Read-only: no edit/delete actions
            ])
            ->bulkActions([
                // Read-only: no bulk actions
            ])
            ->defaultSort('effective_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

