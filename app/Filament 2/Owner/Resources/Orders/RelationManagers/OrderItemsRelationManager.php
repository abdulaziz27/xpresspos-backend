<?php

namespace App\Filament\Owner\Resources\Orders\RelationManagers;

use App\Support\Currency;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Item Pesanan';

    protected static ?string $recordTitleAttribute = 'product_name';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->unit_price ?? 0)))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => Currency::rupiah((float) ($state ?? $record->total_price ?? 0)))
                    ->weight('medium')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->wrap()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}

