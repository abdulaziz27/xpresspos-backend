<?php

namespace App\Filament\Owner\Resources\InventoryAdjustments;

use App\Filament\Owner\Resources\InventoryAdjustments\Pages;
use App\Filament\Owner\Resources\InventoryAdjustments\RelationManagers\ItemsRelationManager;
use App\Models\InventoryAdjustment;
use App\Services\StoreContext;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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

class InventoryAdjustmentResource extends Resource
{
    protected static ?string $model = InventoryAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Penyesuaian Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        $statusOptions = [
            InventoryAdjustment::STATUS_DRAFT => 'Draft',
            InventoryAdjustment::STATUS_APPROVED => 'Disetujui',
            InventoryAdjustment::STATUS_CANCELLED => 'Batal',
        ];

        $reasonOptions = [
            InventoryAdjustment::REASON_COUNT_DIFF => 'Selisih Stok',
            InventoryAdjustment::REASON_EXPIRED => 'Kadaluarsa',
            InventoryAdjustment::REASON_DAMAGE => 'Rusak',
            InventoryAdjustment::REASON_INITIAL => 'Inisialisasi',
        ];

        return $schema->components([
            Section::make('Informasi Penyesuaian')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('adjustment_number')
                                ->label('Nomor Penyesuaian')
                                ->default(fn () => 'ADJ-' . now()->format('ymd-His'))
                                ->maxLength(50)
                                ->required(),
                            Select::make('status')
                                ->label('Status')
                                ->options($statusOptions)
                                ->default(InventoryAdjustment::STATUS_DRAFT)
                                ->required(),
                        ]),
                    Grid::make(2)
                        ->schema([
                            Select::make('reason')
                                ->label('Alasan')
                                ->options($reasonOptions)
                                ->default(InventoryAdjustment::REASON_COUNT_DIFF)
                                ->required(),
                            DateTimePicker::make('adjusted_at')
                                ->label('Tanggal Penyesuaian')
                                ->default(now())
                                ->seconds(false),
                        ]),
                    Section::make('Lokasi')
                        ->schema([
                            Select::make('store_id')
                                ->label('Toko')
                                ->options(self::storeOptions())
                                ->default(fn () => StoreContext::instance()->current(auth()->user()))
                                ->searchable()
                                ->required()
                                ->disabled(fn () => ! auth()->user()?->hasRole('admin_sistem'))
                                ->helperText('Gunakan filter cabang di header untuk mengatur toko aktif.'),
                            Hidden::make('user_id')
                                ->default(fn () => auth()->id()),
                        ])
                        ->columns(1),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(4)
                        ->maxLength(1000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('adjustment_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'gray' => InventoryAdjustment::STATUS_DRAFT,
                        'success' => InventoryAdjustment::STATUS_APPROVED,
                        'danger' => InventoryAdjustment::STATUS_CANCELLED,
                    ]),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        InventoryAdjustment::REASON_COUNT_DIFF => 'Selisih Stok',
                        InventoryAdjustment::REASON_EXPIRED => 'Kadaluarsa',
                        InventoryAdjustment::REASON_DAMAGE => 'Rusak',
                        InventoryAdjustment::REASON_INITIAL => 'Inisialisasi',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Jumlah Item')
                    ->counts('items')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Petugas')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('adjusted_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(self::storeOptions())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        InventoryAdjustment::STATUS_DRAFT => 'Draft',
                        InventoryAdjustment::STATUS_APPROVED => 'Disetujui',
                        InventoryAdjustment::STATUS_CANCELLED => 'Batal',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryAdjustments::route('/'),
            'create' => Pages\CreateInventoryAdjustment::route('/create'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
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


