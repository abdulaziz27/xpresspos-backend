<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\SubscriptionResource\Pages;
use App\Filament\Owner\Resources\SubscriptionResource\RelationManagers;
use App\Models\Subscription;
use App\Services\GlobalFilterService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use BackedEnum;
use App\Support\Currency;
use Filament\Support\Icons\Heroicon;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Langganan';

    protected static ?int $navigationSort = 10;

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Detail Langganan')
                    ->schema([
                        Forms\Components\Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Aktif',
                                'inactive' => 'Tidak Aktif',
                                'cancelled' => 'Dibatalkan',
                                'expired' => 'Kedaluwarsa',
                            ])
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\Select::make('billing_cycle')
                            ->options([
                                'monthly' => 'Bulanan',
                                'annual' => 'Tahunan',
                            ])
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('starts_at')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('ends_at')
                            ->required()
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('trial_ends_at')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('Paket')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'cancelled' => 'Dibatalkan',
                        'expired' => 'Kedaluwarsa',
                        default => ucfirst($state),
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('Penagihan')
                    ->formatStateUsing(fn (string $state): string => $state === 'monthly' ? 'Bulanan' : ($state === 'annual' ? 'Tahunan' : ucfirst($state)))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0)))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Berakhir')
                    ->date()
                    ->sortable()
                    ->color(fn (Subscription $record): string => 
                        $record->ends_at->isPast() ? 'danger' : 
                        ($record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                    ),
                
                Tables\Columns\TextColumn::make('days_until_expiration')
                    ->label('Sisa Hari')
                    ->getStateUsing(fn (Subscription $record): string => 
                        $record->ends_at->isPast() ? 'Kedaluwarsa' : 
                        $record->ends_at->diffInDays() . ' hari'
                    )
                    ->color(fn (Subscription $record): string => 
                        $record->ends_at->isPast() ? 'danger' : 
                        ($record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Tidak Aktif',
                        'cancelled' => 'Dibatalkan',
                        'expired' => 'Kedaluwarsa',
                    ]),
                
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options([
                        'monthly' => 'Bulanan',
                        'annual' => 'Tahunan',
                    ]),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->label('Lihat'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan Langganan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('plan.name')
                                    ->label('Paket'),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'warning',
                                        'cancelled' => 'danger',
                                        'expired' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'active' => 'Aktif',
                                        'inactive' => 'Tidak Aktif',
                                        'cancelled' => 'Dibatalkan',
                                        'expired' => 'Kedaluwarsa',
                                        default => ucfirst($state),
                                    }),
                                
                                Infolists\Components\TextEntry::make('billing_cycle')
                                    ->label('Siklus Penagihan')
                                    ->formatStateUsing(fn (string $state): string => $state === 'monthly' ? 'Bulanan' : ($state === 'annual' ? 'Tahunan' : ucfirst($state))),
                                
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Jumlah')
                                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0))),
                                
                                Infolists\Components\TextEntry::make('starts_at')
                                    ->label('Mulai')
                                    ->date(),
                                
                                Infolists\Components\TextEntry::make('ends_at')
                                    ->label('Berakhir')
                                    ->date()
                                    ->color(fn (Subscription $record): string => 
                                        $record->ends_at->isPast() ? 'danger' : 
                                        ($record->ends_at->diffInDays() <= 7 ? 'warning' : 'success')
                                    ),
                            ]),
                    ]),
                
                Section::make('Riwayat Pembayaran')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('subscriptionPayments')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('amount')
                                            ->label('Jumlah')
                                            ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0))),
                                        
                                        Infolists\Components\TextEntry::make('status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'paid' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                'expired' => 'gray',
                                                default => 'gray',
                                            }),
                                        
                                        Infolists\Components\TextEntry::make('payment_method')
                                            ->label('Method')
                                            ->formatStateUsing(fn ($record): string => 
                                                $record->getPaymentMethodDisplayName()
                                            ),
                                        
                                        Infolists\Components\TextEntry::make('paid_at')
                                            ->label('Dibayar Pada')
                                            ->dateTime()
                                            ->placeholder('Belum dibayar'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var GlobalFilterService $globalFilter */
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();
        
        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }
        
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes();
        
        if (!$tenantId) {
            return $query->whereRaw('1 = 0'); // Return empty query
        }
        
        // Only filter by tenant - store filtering is handled by table filters
        // This ensures page independence from dashboard store filter
        return $query
            ->where('tenant_id', $tenantId)
            ->with(['plan', 'subscriptionPayments', 'invoices', 'usage']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $activeCount = static::getEloquentQuery()
            ->where('status', 'active')
            ->count();
            
        return $activeCount > 0 ? (string) $activeCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        // Subscription dibuat via onboarding/checkout, bukan manual
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Subscription tidak boleh dihapus manual
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Semua field read-only, tidak ada field yang aman untuk diubah
        return false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\SubscriptionPaymentsRelationManager::class,
            RelationManagers\SubscriptionUsageRelationManager::class,
        ];
    }
}