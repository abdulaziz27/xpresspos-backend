<?php

namespace App\Filament\Owner\Resources\PurchaseOrders;

use App\Filament\Owner\Resources\PurchaseOrders\Pages;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
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

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Purchase Order';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi PO')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('Nomor PO')
                                    ->required(),
                                Select::make('supplier_id')
                                    ->label('Pemasok')
                                    ->options(fn () => Supplier::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('ordered_at')
                                    ->label('Tanggal Order'),
                                DatePicker::make('received_at')
                                    ->label('Tanggal Terima'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'approved' => 'Disetujui',
                                        'received' => 'Diterima',
                                        'closed' => 'Selesai',
                                        'cancelled' => 'Batal',
                                    ])
                                    ->default('draft'),
                                TextInput::make('total_amount')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
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
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
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
}

