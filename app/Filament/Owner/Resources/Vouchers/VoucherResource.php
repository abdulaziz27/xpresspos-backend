<?php

namespace App\Filament\Owner\Resources\Vouchers;

use App\Filament\Owner\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Owner\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Owner\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Owner\Resources\Vouchers\RelationManagers\RedemptionsRelationManager;
use App\Models\Promotion;
use App\Models\Voucher;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Voucher')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('code')
                                ->label('Kode')
                                ->required()
                                ->maxLength(50)
                                ->unique(ignoreRecord: true),
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
                        ->options(fn () => Promotion::query()->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->placeholder('Tidak terhubung (opsional)'),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('max_redemptions')
                                ->label('Batas Penukaran')
                                ->numeric()
                                ->minValue(1)
                                ->helperText('Kosongkan untuk tanpa batas'),
                            TextInput::make('redemptions_count')
                                ->label('Telah Ditukar')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false)
                                ->default(0),
                        ]),
                    Grid::make(2)
                        ->schema([
                            DateTimePicker::make('valid_from')
                                ->label('Berlaku Mulai')
                                ->seconds(false)
                                ->required(),
                            DateTimePicker::make('valid_until')
                                ->label('Berlaku Hingga')
                                ->seconds(false)
                                ->required()
                                ->minDate(fn (callable $get) => $get('valid_from') ?: now()),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('promotion.name')
                    ->label('Promo')
                    ->placeholder('-'),
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
                    ->label('Penukaran')
                    ->formatStateUsing(fn ($state, $record) => sprintf('%d / %s', $state ?? 0, $record->max_redemptions ?? '∞')),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Mulai')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berakhir')
                    ->dateTime()
                    ->color(fn ($state) => ($state && now()->greaterThan($state)) ? 'danger' : null),
            ])
            ->defaultSort('valid_from', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'expired' => 'Kadaluarsa',
                    ]),
                Tables\Filters\SelectFilter::make('promotion_id')
                    ->label('Promo')
                    ->options(fn () => Promotion::query()->pluck('name', 'id')),
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
        return parent::getEloquentQuery()->with('promotion');
    }
}


