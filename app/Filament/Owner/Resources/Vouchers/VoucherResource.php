<?php

namespace App\Filament\Owner\Resources\Vouchers;

use App\Filament\Owner\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Owner\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Owner\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Owner\Resources\Vouchers\RelationManagers\RedemptionsRelationManager;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Models\Store;
use App\Enums\AssignmentRoleEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use App\Filament\Traits\HasPlanBasedNavigation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class VoucherResource extends Resource
{
    use HasPlanBasedNavigation;
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Voucher';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Voucher';

    protected static ?int $navigationSort = 20;

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
        return $schema->components([
            Section::make('Informasi Voucher')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('code')
                                ->label('Kode Voucher')
                                ->required()
                                ->maxLength(50)
                                ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                    $tenantId = auth()->user()?->currentTenant()?->id;
                                    if ($tenantId) {
                                        $rule->where('tenant_id', $tenantId);
                                    }
                                    return $rule;
                                })
                                ->helperText('Kode unik untuk voucher'),
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
                    Select::make('promotion_id')
                        ->label('Promo Terkait')
                        ->options(fn () => self::promotionOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('Tidak terhubung (opsional)')
                        ->helperText('Pilih promo jika voucher terkait dengan promo tertentu'),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('max_redemptions')
                                ->label('Batas Pemakaian Total')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('Kosongkan untuk tanpa batas')
                                ->placeholder('Tidak terbatas'),
                            TextInput::make('redemptions_count')
                                ->label('Total Dipakai')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0)
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record) {
                                        $component->state($record->redemptions()->count());
                                    }
                                })
                                ->helperText('Diisi otomatis oleh sistem dari data redemptions'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            DateTimePicker::make('valid_from')
                                ->label('Tanggal Mulai')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                ->dehydrateStateUsing(function ($state) {
                                    if (!$state) {
                                        return null;
                                    }
                                    return Carbon::parse($state)->setSeconds(0);
                                }),
                            DateTimePicker::make('valid_until')
                                ->label('Tanggal Berakhir')
                                ->seconds(false)
                                ->native(false)
                                ->required()
                                ->minDate(fn (callable $get) => $get('valid_from') ?: now())
                                ->dehydrateStateUsing(function ($state) {
                                    if (!$state) {
                                        return null;
                                    }
                                    return Carbon::parse($state)->setSeconds(0);
                                }),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Voucher')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('promotion.name')
                    ->label('Promo Terkait')
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'expired',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('redemptions_count')
                    ->label('Total Dipakai')
                    ->counts('redemptions')
                    ->formatStateUsing(fn ($state, $record) => sprintf('%d / %s', $state, $record->max_redemptions ?? '∞'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->color(fn ($state) => ($state && now()->greaterThan($state)) ? 'danger' : null),
            ])
            ->defaultSort('valid_from', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'expired' => 'Kadaluarsa',
                    ]),
                Filter::make('active_vouchers')
                    ->label('Voucher yang Sedang Berjalan')
                    ->query(function (Builder $query): Builder {
                        return $query->where('status', 'active')
                            ->where('valid_from', '<=', now())
                            ->where('valid_until', '>=', now());
                    }),
                Tables\Filters\SelectFilter::make('promotion_id')
                    ->label('Promo Terkait')
                    ->options(fn () => self::promotionOptions())
                    ->placeholder('Semua Promo'),
                Filter::make('period')
                    ->label('Periode')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('valid_from')
                            ->label('Mulai Dari'),
                        \Filament\Forms\Components\DatePicker::make('valid_until')
                            ->label('Berakhir Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['valid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('valid_from', '>=', $date),
                            )
                            ->when(
                                $data['valid_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('valid_until', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
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
            RedemptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'edit' => EditVoucher::route('/{record}/edit'),
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
            ->with(['promotion', 'redemptions'])
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
                ->whereNull('promotion_id')
                ->orWhereHas('promotion', function (Builder $promotionQuery) use ($storeIds) {
                    $promotionQuery
                        ->whereNull('store_id')
                        ->orWhereIn('store_id', $storeIds);
                });
        });
    }

    protected static function promotionOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $tenantId = $user->currentTenant()?->id;

        if (! $tenantId) {
            return [];
        }

        $query = Promotion::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        if (! static::isOwnerContext($user)) {
            $storeIds = static::accessibleStoreIds($user, $tenantId);

            if (empty($storeIds)) {
                return [];
            }

            $query->where(function (Builder $builder) use ($storeIds) {
                $builder
                    ->whereNull('store_id')
                    ->orWhereIn('store_id', $storeIds);
            });
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
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


