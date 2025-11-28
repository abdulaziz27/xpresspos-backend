<?php

namespace App\Filament\Owner\Resources\Promotions;

use App\Filament\Owner\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Owner\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Owner\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Owner\Resources\Promotions\RelationManagers\ConditionsRelationManager;
use App\Filament\Owner\Resources\Promotions\RelationManagers\RewardsRelationManager;
    use App\Filament\Traits\HasPlanBasedNavigation;
    use App\Models\Promotion;
use App\Models\Store;
use App\Enums\AssignmentRoleEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class PromotionResource extends Resource
{
    use HasPlanBasedNavigation;
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Promosi';

    protected static ?string $modelLabel = 'Promosi';

    protected static ?string $pluralModelLabel = 'Promosi';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Promo & Kampanye';

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasPlanFeature('ALLOW_PROMO');
    }

    public static function canViewAny(): bool
    {
        if (!static::hasPlanFeature('ALLOW_PROMO')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('viewAny', static::$model);
    }

    public static function canCreate(): bool
    {
        if (!static::hasPlanFeature('ALLOW_PROMO')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('create', static::$model);
    }

    public static function canEdit(Model $record): bool
    {
        if (!static::hasPlanFeature('ALLOW_PROMO')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        if (!static::hasPlanFeature('ALLOW_PROMO')) {
            return false;
        }
        $user = auth()->user();
        if (!$user) return false;
        return Gate::forUser($user)->allows('delete', $record);
    }

    public static function form(Schema $schema): Schema
    {
        $storeOptions = self::storeOptions();

        return $schema->components([
            Section::make('Informasi Promo')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nama Promo')
                                ->maxLength(255)
                                ->required(),
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Aktif',
                                    'inactive' => 'Tidak Aktif',
                                    'expired' => 'Kadaluarsa',
                                ])
                                ->default('active')
                                ->required(),
                        ]),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3)
                        ->maxLength(1000),
                    Grid::make(2)
                        ->schema([
                            Select::make('type')
                                ->label('Tipe Promo')
                                ->options([
                                    'AUTOMATIC' => 'Otomatis',
                                    'CODED' => 'Pakai Kode',
                                ])
                                ->default('AUTOMATIC')
                                ->required()
                                ->live(),
                            TextInput::make('code')
                                ->label('Kode Promo')
                                ->maxLength(50)
                                ->required(fn (callable $get) => $get('type') === 'CODED')
                                ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                    $tenantId = auth()->user()?->currentTenant()?->id;
                                    if ($tenantId) {
                                        $rule->where('tenant_id', $tenantId);
                                    }

                                    return $rule;
                                })
                                ->helperText('Isi hanya jika tipe promo menggunakan kode.')
                                ->placeholder('PROMO50'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            Select::make('store_id')
                                ->label('Berlaku untuk Toko')
                                ->options($storeOptions)
                                ->searchable()
                                ->placeholder('Semua Toko')
                                ->helperText('Pilih toko tertentu atau kosongkan untuk semua toko.')
                                ->native(false),
                            Toggle::make('stackable')
                                ->label('Bisa Digabung')
                                ->helperText('Izinkan promo lain berjalan bersamaan.')
                                ->default(false),
                        ]),
                    Grid::make(2)
                        ->schema([
                            DateTimePicker::make('starts_at')
                                ->label('Mulai')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                ->dehydrateStateUsing(function ($state) {
                                    if (!$state) {
                                        return null;
                                    }
                                    return Carbon::parse($state)->setSeconds(0);
                                }),
                            DateTimePicker::make('ends_at')
                                ->label('Berakhir')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                ->minDate(fn (callable $get) => $get('starts_at') ?: now())
                                ->dehydrateStateUsing(function ($state) {
                                    if (!$state) {
                                        return null;
                                    }
                                    return Carbon::parse($state)->setSeconds(0);
                                }),
                        ]),
                    TextInput::make('priority')
                        ->label('Prioritas')
                        ->numeric()
                        ->default(0)
                        ->helperText('Semakin tinggi semakin didahulukan.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Promo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Toko')
                    ->placeholder('Semua Toko'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'CODED' ? 'Kode' : 'Otomatis'),
                Tables\Columns\IconColumn::make('stackable')
                    ->label('Stackable')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'expired',
                    ]),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Berakhir')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioritas')
                    ->sortable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'expired' => 'Kadaluarsa',
                    ]),
                Tables\Filters\Filter::make('active_promotions')
                    ->label('Promo yang Sedang Berjalan')
                    ->query(function (Builder $query): Builder {
                        return $query->where('status', 'active')
                            ->where('starts_at', '<=', now())
                            ->where('ends_at', '>=', now());
                    }),
                Tables\Filters\Filter::make('date_range')
                    ->label('Periode')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('starts_from')
                            ->label('Mulai Dari'),
                        \Filament\Forms\Components\DatePicker::make('starts_until')
                            ->label('Mulai Sampai'),
                        \Filament\Forms\Components\DatePicker::make('ends_from')
                            ->label('Berakhir Dari'),
                        \Filament\Forms\Components\DatePicker::make('ends_until')
                            ->label('Berakhir Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['starts_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('starts_at', '>=', $date),
                            )
                            ->when(
                                $data['starts_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('starts_at', '<=', $date),
                            )
                            ->when(
                                $data['ends_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('ends_at', '>=', $date),
                            )
                            ->when(
                                $data['ends_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('ends_at', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Promo')
                    ->options([
                        'AUTOMATIC' => 'Otomatis',
                        'CODED' => 'Kode',
                    ]),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Toko')
                    ->options(self::storeOptions())
                    ->placeholder('Semua Toko'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ConditionsRelationManager::class,
            RewardsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
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
            ->with('store')
            ->where('tenant_id', $tenantId);

        if (static::isOwnerContext($user)) {
            return $query;
        }

        $storeIds = static::accessibleStoreIds($user, $tenantId);

        if (empty($storeIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($storeIds) {
            $builder
                ->whereNull('store_id')
                ->orWhereIn('store_id', $storeIds);
        });
    }

    protected static function storeOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $tenantId = $user->currentTenant()?->id;

        if (! $tenantId) {
            return [];
        }

        if (static::isOwnerContext($user)) {
            return Store::where('tenant_id', $tenantId)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        return $user->stores()
            ->where('stores.tenant_id', $tenantId)
            ->orderBy('stores.name')
            ->pluck('stores.name', 'stores.id')
            ->toArray();
    }

    protected static function accessibleStoreIds($user, string $tenantId): array
    {
        if (static::isOwnerContext($user)) {
            return Store::where('tenant_id', $tenantId)->pluck('id')->toArray();
        }

        return $user->stores()
            ->where('stores.tenant_id', $tenantId)
            ->pluck('stores.id')
            ->toArray();
    }

    protected static function isOwnerContext($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('owner')) {
            return true;
        }

        if (! method_exists($user, 'storeAssignments')) {
            return false;
        }

        return $user->storeAssignments()
            ->where('assignment_role', AssignmentRoleEnum::OWNER->value)
            ->exists();
    }

}


