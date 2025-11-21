<?php

namespace App\Filament\Owner\Resources\InventoryItems\RelationManagers;

use App\Models\InventoryLot;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LotsRelationManager extends RelationManager
{
    protected static string $relationship = 'lots';

    protected static ?string $title = 'Lot Stok';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('lot_code')
                ->label('Kode Lot')
                ->required(),
            Forms\Components\TextInput::make('initial_qty')
                ->label('Qty Awal')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('remaining_qty')
                ->label('Qty Sisa')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('unit_cost')
                ->label('Biaya Satuan')
                ->numeric()
                ->prefix('Rp')
                ->required(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Aktif',
                    'expired' => 'Kadaluarsa',
                    'depleted' => 'Habis',
                ])
                ->default('active')
                ->required(),
            Forms\Components\DatePicker::make('mfg_date')
                ->label('Tanggal Produksi'),
            Forms\Components\DatePicker::make('exp_date')
                ->label('Tanggal Kadaluarsa'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lot_code')
                    ->label('Lot')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('remaining_qty')
                    ->label('Qty Sisa')
                    ->numeric(3),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'expired',
                        'gray' => 'depleted',
                    ]),
                Tables\Columns\TextColumn::make('exp_date')
                    ->label('Kadaluarsa')
                    ->date()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Tambah Lot'),
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


