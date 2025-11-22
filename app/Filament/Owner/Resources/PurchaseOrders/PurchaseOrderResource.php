<?php

namespace App\Filament\Owner\Resources\PurchaseOrders;

use App\Filament\Owner\Resources\PurchaseOrders\Pages;
use App\Filament\Owner\Resources\PurchaseOrders\RelationManagers\ItemsRelationManager;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\GlobalFilterService;
use App\Support\Currency;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Purchase Order';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 60; // 6. Purchase Order

    public static function form(Schema $schema): Schema
    {
        $statusOptions = [
            PurchaseOrder::STATUS_DRAFT => 'Draft',
            PurchaseOrder::STATUS_APPROVED => 'Disetujui',
            PurchaseOrder::STATUS_RECEIVED => 'Diterima',
            PurchaseOrder::STATUS_CLOSED => 'Selesai',
            PurchaseOrder::STATUS_CANCELLED => 'Batal',
        ];

        return $schema
            ->components([
                Section::make('Informasi PO')
                    ->description('Data dasar purchase order')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('store_id')
                                    ->label('Toko')
                                    ->options(self::storeOptions())
                                    ->default(fn () => self::getDefaultStoreId())
                                    ->required()
                                    ->searchable()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED]))
                                    ->helperText('Gunakan filter cabang di header untuk mengatur toko aktif.'),
                                Select::make('supplier_id')
                                    ->label('Supplier')
                                    ->options(fn () => Supplier::query()
                                        ->where('status', 'active')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('Nomor PO')
                                    ->default(fn () => PurchaseOrder::generatePoNumber())
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
                                Select::make('status')
                                    ->label('Status')
                                    ->options($statusOptions)
                                    ->default(PurchaseOrder::STATUS_DRAFT)
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('ordered_at')
                                    ->label('Tanggal Order')
                                    ->default(now())
                                    ->seconds(false)
                                    ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
                                DateTimePicker::make('received_at')
                                    ->label('Tanggal Diterima')
                                    ->seconds(false)
                                    ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
                            ]),
                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn($state, $record) => $record?->total_amount ? Currency::rupiah((float) $record->total_amount) : Currency::rupiah(0))
                            ->helperText('Dihitung otomatis dari total cost semua item (read-only)'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->disabled(fn ($record) => $record && in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => PurchaseOrder::STATUS_DRAFT,
                        'warning' => PurchaseOrder::STATUS_APPROVED,
                        'success' => PurchaseOrder::STATUS_RECEIVED,
                        'primary' => PurchaseOrder::STATUS_CLOSED,
                        'danger' => PurchaseOrder::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        PurchaseOrder::STATUS_DRAFT => 'Draft',
                        PurchaseOrder::STATUS_APPROVED => 'Disetujui',
                        PurchaseOrder::STATUS_RECEIVED => 'Diterima',
                        PurchaseOrder::STATUS_CLOSED => 'Selesai',
                        PurchaseOrder::STATUS_CANCELLED => 'Batal',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => $state ? Currency::rupiah((float) $state) : Currency::rupiah(0))
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Tanggal Order')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Tanggal Diterima')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(self::storeOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        PurchaseOrder::STATUS_DRAFT => 'Draft',
                        PurchaseOrder::STATUS_APPROVED => 'Disetujui',
                        PurchaseOrder::STATUS_RECEIVED => 'Diterima',
                        PurchaseOrder::STATUS_CLOSED => 'Selesai',
                        PurchaseOrder::STATUS_CANCELLED => 'Batal',
                    ]),

                Filter::make('ordered_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('ordered_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('ordered_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['ordered_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('ordered_at', '>=', $date),
                            )
                            ->when(
                                $data['ordered_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('ordered_at', '<=', $date),
                            );
                    }),

                Filter::make('received_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('received_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('received_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['received_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_at', '>=', $date),
                            )
                            ->when(
                                $data['received_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('received_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => !in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED])),
            ])
            ->bulkActions([
                // No delete bulk action - purchase orders are audit trail documents
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('ordered_at', 'desc')
                            ->orderBy('created_at', 'desc');
            })
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Owner can create purchase orders.
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Owner can edit purchase orders (only when status is not received/closed/cancelled).
     */
    public static function canEdit(Model $record): bool
    {
        return !in_array($record->status, [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CLOSED, PurchaseOrder::STATUS_CANCELLED]);
    }

    /**
     * Owner CANNOT delete purchase orders (audit trail).
     * Purchase orders are financial documents and must be preserved.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Force delete also disabled for audit trail.
     */
    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Restore disabled (no soft deletes for purchase orders).
     */
    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
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

