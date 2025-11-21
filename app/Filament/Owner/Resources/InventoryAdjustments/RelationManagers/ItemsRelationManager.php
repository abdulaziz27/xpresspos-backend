<?php

namespace App\Filament\Owner\Resources\InventoryAdjustments\RelationManagers;

use App\Models\InventoryItem;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Detail Penyesuaian';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('inventory_item_id')
                ->label('Item')
                ->options(fn () => InventoryItem::query()->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('system_qty')
                ->label('Qty Sistem')
                ->numeric()
                ->required()
                ->helperText('Jumlah stok menurut sistem'),
            Forms\Components\TextInput::make('counted_qty')
                ->label('Qty Hasil Hitung')
                ->numeric()
                ->required()
                ->helperText('Jumlah stok hasil pengecekan fisik'),
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
                Tables\Columns\TextColumn::make('system_qty')
                    ->label('Qty Sistem')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('counted_qty')
                    ->label('Qty Hitung')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('difference_qty')
                    ->label('Selisih')
                    ->numeric(3)
                    ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
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


