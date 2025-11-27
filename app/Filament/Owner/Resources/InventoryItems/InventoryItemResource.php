<?php

namespace App\Filament\Owner\Resources\InventoryItems;

use App\Filament\Owner\Resources\InventoryItems\Pages;
use App\Models\InventoryItem;
use App\Models\Uom;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Owner\Resources\InventoryItems\RelationManagers\LotsRelationManager;
use App\Filament\Owner\Resources\InventoryItems\RelationManagers\StockLevelsRelationManager;
use App\Filament\Owner\Resources\InventoryItems\RelationManagers\InventoryMovementsRelationManager;
use App\Filament\Traits\HasPlanBasedNavigation;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class InventoryItemResource extends Resource
{
    use HasPlanBasedNavigation;
    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Bahan';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 10; // 1. Bahan

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Bahan')
                    ->description('Detail dasar bahan / item inventory')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Bahan')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: Gula Pasir, Tepung Terigu'),
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Kode unik bahan (opsional, unique per tenant)'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('category')
                                    ->label('Kategori')
                                    ->maxLength(100)
                                    ->placeholder('Contoh: Bahan Baku, Bumbu, Kemasan')
                                    ->helperText('Kategori untuk pengelompokan bahan'),
                                Select::make('uom_id')
                                    ->label('Satuan (UOM)')
                                    ->options(fn () => Uom::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Satuan dasar untuk bahan ini (kg, liter, pcs, dll)'),
                            ]),
                    ])
                    ->columns(1),

                Section::make('Pengaturan Stok')
                    ->description('Konfigurasi pelacakan stok')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('track_stock')
                                    ->label('Pantau Stok')
                                    ->default(true)
                                    ->helperText('Aktifkan untuk melacak stok bahan ini per store'),
                                Toggle::make('track_lot')
                                    ->label('Pantau Lot')
                                    ->default(false)
                                    ->helperText('Aktifkan untuk melacak lot/batch (exp date, mfg date)'),
                            ]),
                        TextInput::make('min_stock_level')
                            ->label('Min Stok Level (Global)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.001)
                            ->helperText('Level minimum stok global (dapat di-override per store di Stock Levels)'),
                    ])
                    ->columns(1),

                Section::make('Cost Default')
                    ->description('Biaya default per satuan')
                    ->schema([
                        TextInput::make('default_cost')
                            ->label('Default Cost (per unit)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->minValue(0)
                            ->step(0.0001)
                            ->helperText('Biaya default per satuan. Dipakai sebagai unit_cost default di recipe.'),
                    ])
                    ->columns(1),

                Section::make('Status')
                    ->description('Status aktif/nonaktif bahan')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                            ])
                            ->default('active')
                            ->required()
                            ->helperText('Bahan aktif dapat digunakan di resep dan transaksi'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Bahan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('uom.name')
                    ->label('Satuan')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('default_cost')
                    ->label('Default Cost')
                    ->numeric(4)
                    ->prefix('Rp ')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('min_stock_level')
                    ->label('Min Stok')
                    ->numeric(3)
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('track_stock')
                    ->label('Pantau Stok')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('track_lot')
                    ->label('Pantau Lot')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => $state === 'active' ? 'success' : 'gray')
                    ->formatStateUsing(fn($state) => $state === 'active' ? 'Aktif' : 'Tidak Aktif')
                    ->sortable(),

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
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                    ]),

                Tables\Filters\TernaryFilter::make('track_stock')
                    ->label('Pantau Stok')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya yang dipantau')
                    ->falseLabel('Hanya yang tidak dipantau'),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(function () {
                        return InventoryItem::query()
                            ->whereNotNull('category')
                            ->distinct()
                            ->pluck('category', 'category');
                    })
                    ->searchable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('name', 'asc');
            })
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * Hide navigation if tenant doesn't have inventory feature.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::hasPlanFeature('ALLOW_INVENTORY');
    }

    public static function canViewAny(): bool
    {
        if (!static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    /**
     * Owner can create inventory items (if plan allows).
     */
    public static function canCreate(): bool
    {
        if (!static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('create', static::$model);
    }

    /**
     * Owner can edit inventory items (if plan allows).
     */
    public static function canEdit(Model $record): bool
    {
        if (!static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('update', $record);
    }

    /**
     * Owner can delete inventory items (if plan allows).
     * FK constraints will prevent deletion if item is used in recipes, PO, etc.
     */
    public static function canDelete(Model $record): bool
    {
        if (!static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('delete', $record);
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
            StockLevelsRelationManager::class,
            LotsRelationManager::class,
            InventoryMovementsRelationManager::class,
        ];
    }
}

