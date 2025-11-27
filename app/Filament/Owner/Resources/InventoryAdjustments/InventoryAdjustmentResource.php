<?php

namespace App\Filament\Owner\Resources\InventoryAdjustments;

use App\Filament\Owner\Resources\InventoryAdjustments\Pages;
use App\Filament\Owner\Resources\InventoryAdjustments\RelationManagers\ItemsRelationManager;
use App\Models\InventoryAdjustment;
use App\Models\Store;
use App\Enums\AssignmentRoleEnum;
use App\Filament\Traits\HasPlanBasedNavigation;
use App\Support\Currency;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InventoryAdjustmentResource extends Resource
{
    use HasPlanBasedNavigation;
    protected static ?string $model = InventoryAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Penyesuaian Stok';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventori';

    protected static ?int $navigationSort = 30; // 3. Penyesuaian Stok

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
                                ->default(fn () => InventoryAdjustment::generateAdjustmentNumber())
                                ->maxLength(50)
                                ->required()
                                ->disabled(fn ($record) => $record && in_array($record->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED])),
                            Select::make('status')
                                ->label('Status')
                                ->options($statusOptions)
                                ->default(InventoryAdjustment::STATUS_DRAFT)
                                ->required()
                                ->disabled(fn ($record) => $record && in_array($record->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED])),
                        ]),
                    Grid::make(2)
                        ->schema([
                            Select::make('reason')
                                ->label('Alasan')
                                ->options($reasonOptions)
                                ->default(InventoryAdjustment::REASON_COUNT_DIFF)
                                ->required()
                                ->disabled(fn ($record) => $record && in_array($record->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED])),
                            DateTimePicker::make('adjusted_at')
                                ->label('Tanggal Penyesuaian')
                                ->default(now())
                                ->seconds(false)
                                ->disabled(fn ($record) => $record && in_array($record->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED])),
                        ]),
                    Grid::make(1)
                        ->schema([
                            Select::make('store_id')
                                ->label('Toko')
                                ->options(fn () => self::storeOptions())
                                ->default(fn () => self::getDefaultStoreId())
                                ->searchable()
                                ->required()
                                ->disabled(fn ($record) => $record && in_array($record->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED]))
                                ->helperText('Gunakan filter cabang di header untuk mengatur toko aktif.'),
                            Hidden::make('user_id')
                                ->default(fn () => auth()->id()),
                        ]),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(4)
                        ->maxLength(1000)
                        ->disabled(fn ($record) => $record && in_array($record->status, [InventoryAdjustment::STATUS_APPROVED, InventoryAdjustment::STATUS_CANCELLED])),
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
                Tables\Columns\TextColumn::make('total_items')
                    ->label('Jumlah Item')
                    ->getStateUsing(fn ($record) => $record->total_items)
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Total Nilai')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->getStateUsing(fn ($record) => $record->total_value)
                    ->sortable()
                    ->alignEnd()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Petugas')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('adjusted_at')
                    ->label('Tanggal')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(self::storeOptions())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        InventoryAdjustment::STATUS_DRAFT => 'Draft',
                        InventoryAdjustment::STATUS_APPROVED => 'Disetujui',
                        InventoryAdjustment::STATUS_CANCELLED => 'Batal',
                    ]),
                Filter::make('adjusted_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('adjusted_from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('adjusted_until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['adjusted_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('adjusted_at', '>=', $date),
                            )
                            ->when(
                                $data['adjusted_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('adjusted_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === InventoryAdjustment::STATUS_DRAFT),
            ])
            ->bulkActions([
                // No delete bulk action - adjustments are audit trail documents
            ])
            ->modifyQueryUsing(function ($query) {
                return $query->orderBy('adjusted_at', 'desc')
                            ->orderBy('created_at', 'desc');
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
     * Owner can create inventory adjustments (if plan allows).
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
     * Owner can edit inventory adjustments (only when status is draft).
     */
    public static function canEdit(Model $record): bool
    {
        if (!static::hasPlanFeature('ALLOW_INVENTORY')) {
            return false;
        }
        if ($record->status !== InventoryAdjustment::STATUS_DRAFT) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('update', $record);
    }

    /**
     * Owner CANNOT delete inventory adjustments (audit trail).
     * Adjustments are financial documents and must be preserved.
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
     * Restore disabled (no soft deletes for adjustments).
     */
    public static function canRestore(Model $record): bool
    {
        return false;
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
            'view' => Pages\ViewInventoryAdjustment::route('/{record}'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }

    protected static function storeOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $isOwner = $user->hasRole('owner') || $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();

        if ($isOwner) {
            $tenantId = $user->currentTenant()?->id;
            if (! $tenantId) {
                return [];
            }

            return \App\Models\Store::where('tenant_id', $tenantId)
                ->pluck('name', 'id')
                ->toArray();
        }

        return $user->stores()
            ->pluck('stores.name', 'stores.id')
            ->toArray();
    }

    protected static function getDefaultStoreId(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $primaryStore = $user->primaryStore();
        if ($primaryStore) {
            return $primaryStore->id;
        }

        return $user->stores()->first()?->id;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $tenantId = $user?->currentTenant()?->id;

        if (! $tenantId) {
            return parent::getEloquentQuery()
                ->withoutGlobalScopes()
                ->whereRaw('1 = 0');
        }

        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['store', 'user'])
            ->whereHas('store', fn (Builder $storeQuery) => $storeQuery->where('tenant_id', $tenantId));

        $isOwner = $user->hasRole('owner') || $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();

        if ($isOwner) {
            return $query;
        }

        $storeIds = $user->stores()->pluck('stores.id')->toArray();

        if (empty($storeIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('store_id', $storeIds);
    }
}


