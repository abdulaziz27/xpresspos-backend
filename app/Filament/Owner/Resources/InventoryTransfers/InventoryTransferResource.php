<?php

namespace App\Filament\Owner\Resources\InventoryTransfers;

use App\Filament\Owner\Resources\InventoryTransfers\Pages;
use App\Models\InventoryTransfer;
use App\Models\Store;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryTransferResource extends Resource
{
    protected static ?string $model = InventoryTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Transfer Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Transfer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('from_store_id')
                                    ->label('Dari Toko')
                                    ->options(fn () => Store::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                                Select::make('to_store_id')
                                    ->label('Ke Toko')
                                    ->options(fn () => Store::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('transfer_number')
                                    ->label('Nomor Transfer')
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'approved' => 'Disetujui',
                                        'shipped' => 'Dikirim',
                                        'received' => 'Diterima',
                                        'cancelled' => 'Batal',
                                    ])
                                    ->default('draft'),
                            ]),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromStore.name')
                    ->label('Dari'),
                Tables\Columns\TextColumn::make('toStore.name')
                    ->label('Ke'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'approved',
                        'primary' => 'shipped',
                        'success' => 'received',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Disetujui',
                        'shipped' => 'Dikirim',
                        'received' => 'Diterima',
                        'cancelled' => 'Batal',
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
            'index' => Pages\ListInventoryTransfers::route('/'),
            'create' => Pages\CreateInventoryTransfer::route('/create'),
            'edit' => Pages\EditInventoryTransfer::route('/{record}/edit'),
        ];
    }
}

