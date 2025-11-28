<?php

namespace App\Filament\Owner\Resources\TenantAddOns;

use App\Filament\Owner\Resources\TenantAddOns\Pages;
use App\Models\AddOn;
use App\Models\TenantAddOn;
use App\Services\GlobalFilterService;
use App\Support\Currency;
use BackedEnum;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantAddOnResource extends Resource
{
    protected static ?string $model = TenantAddOn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Add-ons';

    protected static ?string $modelLabel = 'Add-on';

    protected static ?string $pluralModelLabel = 'Add-ons';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Detail Add-on')
                    ->schema([
                        Forms\Components\Select::make('add_on_id')
                            ->label('Add-on')
                            ->relationship('addOn', 'name')
                            ->required()
                            ->disabled()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Jumlah Unit')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->disabled(),

                        Forms\Components\Select::make('billing_cycle')
                            ->label('Siklus Penagihan')
                            ->options([
                                'monthly' => 'Bulanan',
                                'annual' => 'Tahunan',
                            ])
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('price')
                            ->label('Harga')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktif',
                                'cancelled' => 'Dibatalkan',
                                'expired' => 'Kedaluwarsa',
                            ])
                            ->required()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Mulai')
                            ->required()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Berakhir')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Dibatalkan Pada')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('addOn.name')
                    ->label('Add-on')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('addOn.description')
                    ->label('Deskripsi')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah Unit')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Siklus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'monthly' ? 'Bulanan' : 'Tahunan')
                    ->color(fn (string $state): string => $state === 'monthly' ? 'info' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->formatStateUsing(fn ($state) => Currency::rupiah((float) $state))
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'cancelled' => 'Dibatalkan',
                        'expired' => 'Kedaluwarsa',
                        default => ucfirst($state),
                    })
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('latestPayment.status')
                    ->label('Status Pembayaran')
                    ->colors([
                        'success' => fn (?string $state) => $state === 'paid',
                        'warning' => fn (?string $state) => $state === 'pending',
                        'danger' => fn (?string $state) => $state === 'failed',
                        'gray' => fn (?string $state) => $state === 'expired' || is_null($state),
                    ])
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::ucfirst($state) : 'Belum ada')
                    ->icons([
                        'heroicon-o-check-circle' => 'paid',
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-x-circle' => 'failed',
                        'heroicon-o-exclamation-triangle' => 'expired',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('latestPayment.expires_at')
                    ->label('Jatuh Tempo')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->color(function ($state) {
                        if (blank($state)) {
                            return null;
                        }

                        $expiresAt = $state instanceof \Carbon\Carbon ? $state : \Carbon\Carbon::parse($state);

                        if ($expiresAt->isPast()) {
                            return 'danger';
                        }

                        return $expiresAt->diffInHours(now()) <= 24 ? 'warning' : 'gray';
                    })
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('latestPayment.invoice_url')
                    ->label('Invoice')
                    ->formatStateUsing(fn ($state, TenantAddOn $record) => $record->latestPayment?->xendit_invoice_id ?? '—')
                    ->url(fn (TenantAddOn $record) => $record->latestPayment?->invoice_url, shouldOpenInNewTab: true)
                    ->copyable(fn (TenantAddOn $record) => (string) $record->latestPayment?->invoice_url)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Berakhir')
                    ->dateTime()
                    ->sortable()
                    ->color(fn (TenantAddOn $record): string => 
                        $record->ends_at && $record->ends_at->isPast() ? 'danger' : 
                        ($record->ends_at && $record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                    )
                    ->placeholder('Tidak kedaluwarsa'),

                Tables\Columns\TextColumn::make('total_additional_limit')
                    ->label('Total Bonus Limit')
                    ->getStateUsing(fn (TenantAddOn $record): string => 
                        number_format($record->getTotalAdditionalLimit())
                    )
                    ->badge()
                    ->color('success')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'cancelled' => 'Dibatalkan',
                        'expired' => 'Kedaluwarsa',
                    ]),

                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->label('Siklus Penagihan')
                    ->options([
                        'monthly' => 'Bulanan',
                        'annual' => 'Tahunan',
                    ]),

                Tables\Filters\SelectFilter::make('add_on_id')
                    ->label('Add-on')
                    ->relationship('addOn', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->url(fn (TenantAddOn $record): string => static::getUrl('view', ['record' => $record]))
                    ->color('primary'),
            ])
            ->bulkActions([
                // No bulk actions for add-ons
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Informasi Add-on')
                    ->schema([
                        Infolists\Components\TextEntry::make('addOn.name')
                            ->label('Add-on'),
                        Infolists\Components\TextEntry::make('addOn.description')
                            ->label('Deskripsi')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('quantity')
                            ->label('Jumlah Unit'),
                        Infolists\Components\TextEntry::make('billing_cycle')
                            ->label('Siklus Penagihan')
                            ->formatStateUsing(fn (string $state): string => $state === 'monthly' ? 'Bulanan' : 'Tahunan'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'cancelled' => 'danger',
                                'expired' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'active' => 'Aktif',
                                'cancelled' => 'Dibatalkan',
                                'expired' => 'Kedaluwarsa',
                                default => ucfirst($state),
                            }),
                        Infolists\Components\TextEntry::make('starts_at')
                            ->label('Mulai')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('ends_at')
                            ->label('Berakhir')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Tidak terbatas'),
                        Infolists\Components\TextEntry::make('cancelled_at')
                            ->label('Dibatalkan Pada')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(2),

                \Filament\Schemas\Components\Section::make('Pembayaran Terbaru')
                    ->schema([
                        Infolists\Components\TextEntry::make('latestPayment.xendit_invoice_id')
                            ->label('Invoice ID')
                            ->copyable()
                            ->placeholder('Belum ada'),
                        Infolists\Components\TextEntry::make('latestPayment.status')
                            ->label('Status Pembayaran')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                'expired' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => $state ? Str::ucfirst($state) : 'Belum ada'),
                        Infolists\Components\TextEntry::make('latestPayment.amount')
                            ->label('Jumlah')
                            ->formatStateUsing(fn ($state) => $state ? Currency::rupiah($state) : '-'),
                        Infolists\Components\TextEntry::make('latestPayment.expires_at')
                            ->label('Jatuh Tempo')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-')
                            ->helperText(fn ($record) => $record->latestPayment?->expires_at?->diffForHumans()),
                        Infolists\Components\TextEntry::make('latestPayment.invoice_url')
                            ->label('Link Invoice')
                            ->url(fn ($record) => $record->latestPayment?->invoice_url, true)
                            ->copyable(fn ($record) => $record->latestPayment?->invoice_url)
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('latestPayment.last_reminder_sent_at')
                            ->label('Reminder Terakhir')
                            ->since()
                            ->placeholder('Belum pernah'),
                        Infolists\Components\TextEntry::make('latestPayment.reminder_count')
                            ->label('Jumlah Reminder')
                            ->badge()
                            ->color('info')
                            ->default(0),
                    ])
                    ->visible(fn ($record) => (bool) $record->latestPayment),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Owner\Resources\TenantAddOns\RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantAddOns::route('/'),
            'create' => Pages\CreateTenantAddOn::route('/create'),
            'view' => Pages\ViewTenantAddOn::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['addOn', 'tenant']);

        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();

        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }

        if (!$tenantId) {
            return $query->whereRaw('1 = 0'); // Return empty query
        }

        return $query->where('tenant_id', $tenantId)
            ->with(['addOn', 'latestPayment']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('owner');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('owner');
    }

    public static function canEdit(Model $record): bool
    {
        return false; // Add-ons cannot be edited, only cancelled
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Add-ons cannot be deleted
    }
}

