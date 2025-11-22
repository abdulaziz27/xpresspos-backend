<?php

namespace App\Filament\Owner\Resources\Vouchers;

use App\Filament\Owner\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Owner\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Owner\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Owner\Resources\Vouchers\RelationManagers\RedemptionsRelationManager;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Services\GlobalFilterService;
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
use Illuminate\Database\Eloquent\Builder;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Voucher';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Voucher';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Promo & Kampanye';

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canViewAny(): bool
    {
        return true;
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
                        ->options(fn () => Promotion::query()
                            ->where('tenant_id', auth()->user()?->currentTenant()?->id)
                            ->pluck('name', 'id'))
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
                    ->options(fn () => Promotion::query()
                        ->where('tenant_id', auth()->user()?->currentTenant()?->id)
                        ->pluck('name', 'id'))
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
        $query = parent::getEloquentQuery()
            ->with(['promotion', 'redemptions']);

        // Tenant scope sudah otomatis via TenantScope di model
        // Tapi kita pastikan juga filter promotion sesuai tenant
        $tenantId = auth()->user()?->currentTenant()?->id;
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }
}


