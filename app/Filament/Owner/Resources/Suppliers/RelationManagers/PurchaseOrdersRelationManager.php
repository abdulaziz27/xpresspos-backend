<?php

namespace App\Filament\Owner\Resources\Suppliers\RelationManagers;

use App\Filament\Owner\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static ?string $title = 'Purchase Order';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Nomor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'approved',
                        'primary' => 'received',
                        'success' => 'closed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR', true),
                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Tgl Order')
                    ->date(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Buka')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => PurchaseOrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([])
            ->bulkActions([]);
    }
}


