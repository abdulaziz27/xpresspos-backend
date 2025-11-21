<?php

namespace App\Filament\Owner\Resources\Promotions;

use App\Filament\Owner\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Owner\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Owner\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Owner\Resources\Promotions\RelationManagers\ConditionsRelationManager;
use App\Filament\Owner\Resources\Promotions\RelationManagers\RewardsRelationManager;
use App\Models\Promotion;
use App\Services\GlobalFilterService;
use BackedEnum;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Promosi';

    protected static ?string $modelLabel = 'Promosi';

    protected static ?string $pluralModelLabel = 'Promosi';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Promo & Kampanye';

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
                                ->label('Cabang Khusus')
                                ->options($storeOptions)
                                ->default(fn () => self::defaultStoreId())
                                ->searchable()
                                ->placeholder('Semua Cabang')
                                ->helperText('Kosongkan jika berlaku untuk semua cabang.')
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
                                ->required(),
                            DateTimePicker::make('ends_at')
                                ->label('Berakhir')
                                ->seconds(false)
                                ->required()
                                ->minDate(fn (callable $get) => $get('starts_at') ?: now()),
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
                    ->label('Cabang')
                    ->placeholder('Semua Cabang'),
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
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Promo')
                    ->options([
                        'AUTOMATIC' => 'Otomatis',
                        'CODED' => 'Kode',
                    ]),
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('Cabang')
                    ->options(self::storeOptions())
                    ->placeholder('Semua Cabang'),
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
        $query = parent::getEloquentQuery()
            ->with('store');

        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $storeIds = $globalFilter->getStoreIdsForCurrentTenant();

        if (! empty($storeIds)) {
            $query->where(function (Builder $query) use ($storeIds) {
                $query
                    ->whereNull('store_id')
                    ->orWhereIn('store_id', $storeIds);
            });
        }

        return $query;
    }

    protected static function storeOptions(): array
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getAvailableStores(auth()->user())
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function defaultStoreId(): ?string
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);

        return $globalFilter->getCurrentStoreId()
            ?? ($globalFilter->getStoreIdsForCurrentTenant()[0] ?? null);
    }
}


