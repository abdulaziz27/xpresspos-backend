<?php

namespace App\Filament\Owner\Resources\PurchaseOrders;

use App\Filament\Owner\Resources\PurchaseOrders\Pages;
use App\Filament\Owner\Resources\PurchaseOrders\RelationManagers\ItemsRelationManager;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\GlobalFilterService;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Purchase Order';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        $storeOptions = self::storeOptions();

        return $schema
            ->components([
                Section::make('Informasi PO')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('store_id')
                                    ->label('Toko')
                                    ->options($storeOptions)
                                    ->default(fn () => self::getDefaultStoreId())
                                    ->required()
                                    ->searchable()
                                    ->helperText('Gunakan filter cabang di header untuk mengatur toko aktif.'),
                                Select::make('supplier_id')
                                    ->label('Pemasok')
                                    ->options(fn () => Supplier::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('Nomor PO')
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'approved' => 'Disetujui',
                                        'received' => 'Diterima',
                                        'closed' => 'Selesai',
                                        'cancelled' => 'Batal',
                                    ])
                                    ->default('draft'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('ordered_at')
                                    ->label('Tanggal Order'),
                                DatePicker::make('received_at')
                                    ->label('Tanggal Terima'),
                            ]),
                        TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->helperText('Nilai total dihitung dari detail item.'),
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
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Pemasok')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'approved',
                        'success' => 'received',
                        'primary' => 'closed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR', true)
                    ->label('Total')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Tanggal Order')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(self::storeOptions())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Disetujui',
                        'received' => 'Diterima',
                        'closed' => 'Selesai',
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    protected static function storeOptions(): array
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getAvailableStores(auth()->user())
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function getDefaultStoreId(): ?string
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getCurrentStoreId()
            ?? ($globalFilter->getStoreIdsForCurrentTenant()[0] ?? null);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['store', 'supplier']);

        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        if (! empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }

        return $query;
    }
}

