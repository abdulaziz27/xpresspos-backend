<?php

namespace App\Filament\Owner\Resources\PurchaseOrders\RelationManagers;

use App\Models\InventoryItem;
use App\Models\Uom;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Detail Order';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('inventory_item_id')
                ->label('Item')
                ->options(fn () => InventoryItem::query()->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('uom_id')
                ->label('Satuan')
                ->options(fn () => Uom::query()->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('quantity_ordered')
                ->label('Qty Dipesan')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('quantity_received')
                ->label('Qty Diterima')
                ->numeric()
                ->default(0),
            Forms\Components\TextInput::make('unit_cost')
                ->label('Biaya Satuan')
                ->numeric()
                ->prefix('Rp')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('uom.name')
                    ->label('Satuan')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quantity_ordered')
                    ->label('Dipesan')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('quantity_received')
                    ->label('Diterima')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Biaya Satuan')
                    ->money('IDR', true),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total')
                    ->money('IDR', true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Item'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}


