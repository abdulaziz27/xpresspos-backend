<?php

namespace App\Filament\Owner\Resources\InventoryLots;

use App\Filament\Owner\Resources\InventoryLots\Pages;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Services\StoreContext;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryLotResource extends Resource
{
    protected static ?string $model = InventoryLot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $navigationLabel = 'Lot Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 20;

    /**
     * Hide from navigation - accessible via RelationManager from InventoryItemResource.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Lot')
                    ->schema([
                        Select::make('store_id')
                            ->label('Toko')
                            ->options(self::storeOptions())
                            ->default(fn () => StoreContext::instance()->current(auth()->user()))
                            ->required()
                            ->searchable()
                            ->disabled(fn () => ! auth()->user()?->hasRole('admin_sistem')),
                        Select::make('inventory_item_id')
                            ->label('Item')
                            ->options(fn () => InventoryItem::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('lot_code')
                                    ->label('Kode Lot')
                                    ->required(),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Aktif',
                                        'expired' => 'Kadaluarsa',
                                        'depleted' => 'Habis',
                                    ])
                                    ->default('active'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('mfg_date')
                                    ->label('Tanggal Produksi'),
                                DatePicker::make('exp_date')
                                    ->label('Tanggal Kadaluarsa'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('initial_qty')
                                    ->label('Qty Awal')
                                    ->numeric()
                                    ->required(),
                                TextInput::make('remaining_qty')
                                    ->label('Qty Tersisa')
                                    ->numeric()
                                    ->required(),
                            ]),
                        TextInput::make('unit_cost')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lot_code')
                    ->label('Lot')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('remaining_qty')
                    ->label('Sisa')
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
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'expired' => 'Kadaluarsa',
                        'depleted' => 'Habis',
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
            'index' => Pages\ListInventoryLots::route('/'),
            'create' => Pages\CreateInventoryLot::route('/create'),
            'edit' => Pages\EditInventoryLot::route('/{record}/edit'),
        ];
    }

    protected static function storeOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return StoreContext::instance()
            ->accessibleStores($user)
            ->pluck('name', 'id')
            ->toArray();
    }
}

