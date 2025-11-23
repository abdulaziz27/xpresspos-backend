<?php

namespace App\Filament\Owner\Resources\InventoryTransfers;

use App\Filament\Owner\Resources\InventoryTransfers\Pages;
use App\Filament\Owner\Resources\InventoryTransfers\RelationManagers\ItemsRelationManager;
use App\Models\InventoryTransfer;
use App\Services\GlobalFilterService;
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

class InventoryTransferResource extends Resource
{
    protected static ?string $model = InventoryTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Transfer Antar Toko';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 40; // 4. Transfer Antar Toko

    /**
     * Hide from navigation if tenant only has 1 store (no need for transfers).
     */
    public static function shouldRegisterNavigation(): bool
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $stores = $globalFilter->getAvailableStores();
        
        // Hide if tenant has only 1 store or less
        return $stores->count() > 1;
    }

    public static function form(Schema $schema): Schema
    {
        $statusOptions = [
            InventoryTransfer::STATUS_DRAFT => 'Draft',
            InventoryTransfer::STATUS_APPROVED => 'Disetujui',
            InventoryTransfer::STATUS_SHIPPED => 'Dikirim',
            InventoryTransfer::STATUS_RECEIVED => 'Diterima',
            InventoryTransfer::STATUS_CANCELLED => 'Batal',
        ];

        return $schema
            ->components([
                Section::make('Informasi Transfer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('from_store_id')
                                    ->label('Dari Toko')
                                    ->options(fn () => self::storeOptions())
                                    ->default(fn () => self::getDefaultStoreId())
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_SHIPPED, InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED]))
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Reset to_store_id if same as from_store_id
                                        $toStoreId = $set('to_store_id');
                                        if ($state && $toStoreId === $state) {
                                            $set('to_store_id', null);
                                        }
                                    }),
                                Select::make('to_store_id')
                                    ->label('Ke Toko')
                                    ->options(fn (callable $get) => array_filter(
                                        self::storeOptions(),
                                        fn($id) => $id !== $get('from_store_id'),
                                        ARRAY_FILTER_USE_KEY
                                    ))
                                    ->searchable()
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED]))
                                    ->helperText('Toko tujuan harus berbeda dengan toko asal'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('transfer_number')
                                    ->label('Nomor Transfer')
                                    ->default(fn () => InventoryTransfer::generateTransferNumber())
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_SHIPPED, InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED])),
                                Select::make('status')
                                    ->label('Status')
                                    ->options($statusOptions)
                                    ->default(InventoryTransfer::STATUS_DRAFT)
                                    ->required()
                                    ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED])),
                            ]),
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('shipped_at')
                                    ->label('Tanggal Dikirim')
                                    ->seconds(false)
                                    ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED])),
                                DateTimePicker::make('received_at')
                                    ->label('Tanggal Diterima')
                                    ->seconds(false)
                                    ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED])),
                            ]),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->disabled(fn ($record) => $record && in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED])),
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
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => InventoryTransfer::STATUS_DRAFT,
                        'warning' => InventoryTransfer::STATUS_APPROVED,
                        'primary' => InventoryTransfer::STATUS_SHIPPED,
                        'success' => InventoryTransfer::STATUS_RECEIVED,
                        'danger' => InventoryTransfer::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        InventoryTransfer::STATUS_DRAFT => 'Draft',
                        InventoryTransfer::STATUS_APPROVED => 'Disetujui',
                        InventoryTransfer::STATUS_SHIPPED => 'Dikirim',
                        InventoryTransfer::STATUS_RECEIVED => 'Diterima',
                        InventoryTransfer::STATUS_CANCELLED => 'Batal',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_items')
                    ->label('Jumlah Item')
                    ->getStateUsing(fn ($record) => $record->total_items)
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Total Qty')
                    ->getStateUsing(fn ($record) => $record->total_qty)
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shipped_at')
                    ->label('Dikirim')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Diterima')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        InventoryTransfer::STATUS_DRAFT => 'Draft',
                        InventoryTransfer::STATUS_APPROVED => 'Disetujui',
                        InventoryTransfer::STATUS_SHIPPED => 'Dikirim',
                        InventoryTransfer::STATUS_RECEIVED => 'Diterima',
                        InventoryTransfer::STATUS_CANCELLED => 'Batal',
                    ]),
                Tables\Filters\SelectFilter::make('from_store_id')
                    ->label('Dari Toko')
                    ->options(self::storeOptions())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('to_store_id')
                    ->label('Ke Toko')
                    ->options(self::storeOptions())
                    ->searchable()
                    ->preload(),
                Filter::make('shipped_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('shipped_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('shipped_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['shipped_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('shipped_at', '>=', $date),
                            )
                            ->when(
                                $data['shipped_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('shipped_at', '<=', $date),
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
                    ->visible(fn ($record) => !in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED])),
            ])
            ->bulkActions([
                // No delete bulk action - transfers are audit trail documents
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('created_at', 'desc');
            })
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Owner can create inventory transfers (if tenant has more than 1 store).
     */
    public static function canCreate(): bool
    {
        /** @var \App\Services\GlobalFilterService $globalFilter */
        $globalFilter = app(\App\Services\GlobalFilterService::class);
        $stores = $globalFilter->getAvailableStores();
        
        // Can create if tenant has more than 1 store
        return $stores->count() > 1;
    }

    /**
     * Owner can edit inventory transfers (only when status is not received/cancelled).
     */
    public static function canEdit(Model $record): bool
    {
        return !in_array($record->status, [InventoryTransfer::STATUS_RECEIVED, InventoryTransfer::STATUS_CANCELLED]);
    }

    /**
     * Owner CANNOT delete inventory transfers (audit trail).
     * Transfers are movement documents and must be preserved.
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
     * Restore disabled (no soft deletes for transfers).
     */
    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransfers::route('/'),
            'create' => Pages\CreateInventoryTransfer::route('/create'),
            'view' => Pages\ViewInventoryTransfer::route('/{record}'),
            'edit' => Pages\EditInventoryTransfer::route('/{record}/edit'),
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
            ->with(['fromStore', 'toStore']);

        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        if (! empty($storeIds)) {
            $query->where(function (Builder $query) use ($storeIds) {
                $query
                    ->whereIn('from_store_id', $storeIds)
                    ->orWhereIn('to_store_id', $storeIds);
            });
        }

        return $query;
    }
}

