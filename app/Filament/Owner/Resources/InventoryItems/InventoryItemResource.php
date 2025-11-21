<?php

namespace App\Filament\Owner\Resources\InventoryItems;

use App\Filament\Owner\Resources\InventoryItems\Pages;
use App\Models\InventoryItem;
use App\Models\Uom;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Owner\Resources\InventoryItems\RelationManagers\LotsRelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Item Inventori';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Item')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(100),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('category')
                                    ->label('Kategori')
                                    ->maxLength(100),
                                Select::make('uom_id')
                                    ->label('Satuan')
                                    ->options(fn () => Uom::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_stock_level')
                                    ->label('Min Stok')
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('default_cost')
                                    ->label('Biaya Default')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Toggle::make('track_lot')
                                    ->label('Pantau Lot')
                                    ->default(false),
                                Toggle::make('track_stock')
                                    ->label('Pantau Stok')
                                    ->default(true),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                    ])
                                    ->default('active'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uom.name')
                    ->label('Satuan'),
                Tables\Columns\IconColumn::make('track_stock')
                    ->label('Pantau Stok')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                    ]),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            LotsRelationManager::class,
        ];
    }
}

