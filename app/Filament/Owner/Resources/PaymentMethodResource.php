<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod;
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
use Filament\Support\Icons\Heroicon;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Metode Pembayaran';

    protected static ?int $navigationSort = 50;

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Detail Metode Pembayaran')
                    ->schema([
                        Forms\Components\TextInput::make('gateway')
                            ->label('Gateway')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('type')
                            ->label('Tipe')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('last_four')
                            ->label('Last 4 Digits')
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('expires_at')
                            ->label('Kedaluwarsa')
                            ->disabled(),
                        
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pemilik')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                        'stripe' => 'Stripe',
                        'midtrans' => 'Midtrans',
                        'xendit' => 'Xendit',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'card' => 'success',
                        'va' => 'warning',
                        'digital_wallet' => 'info',
                        'qris' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'card' => 'Kartu',
                        'bank_account' => 'Akun Bank',
                        'bank_transfer' => 'Transfer Bank',
                        'digital_wallet' => 'Dompet Digital',
                        'va' => 'Virtual Account',
                        'qris' => 'QRIS',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Metode Pembayaran')
                    ->getStateUsing(fn (PaymentMethod $record): string => $record->display_name)
                    ->searchable(),
                
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-star')
                    ->falseColor('gray'),
                
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Kedaluwarsa')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (PaymentMethod $record): string => 
                        $record->isExpired() ? 'danger' : 
                        ($record->expires_at && $record->expires_at->diffInDays() <= 30 ? 'warning' : null)
                    )
                    ->placeholder('-'),
                
                Tables\Columns\IconColumn::make('is_usable')
                    ->label('Dapat Digunakan')
                    ->getStateUsing(fn (PaymentMethod $record): bool => $record->isUsable())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label('Gateway')
                    ->options([
                        'stripe' => 'Stripe',
                        'midtrans' => 'Midtrans',
                        'xendit' => 'Xendit',
                    ]),
                
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe')
                    ->options([
                        'card' => 'Kartu',
                        'bank_account' => 'Akun Bank',
                        'bank_transfer' => 'Transfer Bank',
                        'digital_wallet' => 'Dompet Digital',
                        'va' => 'Virtual Account',
                        'qris' => 'QRIS',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default')
                    ->placeholder('Semua')
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak'),
                
                Tables\Filters\Filter::make('expired')
                    ->label('Kedaluwarsa')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('expires_at')
                              ->where('expires_at', '<', now())
                    ),
                
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Akan Kedaluwarsa (≤30 hari)')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('expires_at')
                              ->where('expires_at', '>', now())
                              ->where('expires_at', '<=', now()->addDays(30))
                    ),
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
                Section::make('Informasi Metode Pembayaran')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Pemilik'),
                                
                                Infolists\Components\TextEntry::make('gateway')
                                    ->label('Gateway')
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn (string $state): string => match (strtolower($state)) {
                                        'stripe' => 'Stripe',
                                        'midtrans' => 'Midtrans',
                                        'xendit' => 'Xendit',
                                        default => ucfirst($state),
                                    }),
                                
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Tipe')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'card' => 'success',
                                        'va' => 'warning',
                                        'digital_wallet' => 'info',
                                        'qris' => 'primary',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'card' => 'Kartu',
                                        'bank_account' => 'Akun Bank',
                                        'bank_transfer' => 'Transfer Bank',
                                        'digital_wallet' => 'Dompet Digital',
                                        'va' => 'Virtual Account',
                                        'qris' => 'QRIS',
                                        default => ucfirst(str_replace('_', ' ', $state)),
                                    }),
                                
                                Infolists\Components\TextEntry::make('display_name')
                                    ->label('Metode Pembayaran')
                                    ->getStateUsing(fn (PaymentMethod $record): string => $record->display_name),
                                
                                Infolists\Components\TextEntry::make('masked_number')
                                    ->label('Nomor')
                                    ->getStateUsing(fn (PaymentMethod $record): string => $record->masked_number),
                                
                                Infolists\Components\IconEntry::make('is_default')
                                    ->label('Default')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-star')
                                    ->trueColor('warning')
                                    ->falseIcon('heroicon-o-star')
                                    ->falseColor('gray'),
                                
                                Infolists\Components\TextEntry::make('expires_at')
                                    ->label('Kedaluwarsa')
                                    ->date('d M Y')
                                    ->color(fn (PaymentMethod $record): string => 
                                        $record->isExpired() ? 'danger' : 
                                        ($record->expires_at && $record->expires_at->diffInDays() <= 30 ? 'warning' : null)
                                    )
                                    ->placeholder('Tidak kedaluwarsa'),
                                
                                Infolists\Components\IconEntry::make('is_usable')
                                    ->label('Dapat Digunakan')
                                    ->getStateUsing(fn (PaymentMethod $record): bool => $record->isUsable())
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->trueColor('success')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->falseColor('danger'),
                                
                                Infolists\Components\TextEntry::make('gateway_id')
                                    ->label('Gateway ID')
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),
                    ]),
                
                Section::make('Penggunaan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('payments_count')
                                    ->label('Total Pembayaran')
                                    ->getStateUsing(fn (PaymentMethod $record): string => 
                                        number_format($record->payments()->count())
                                    ),
                            ]),
                    ])
                    ->collapsible(),
                
                Section::make('Timeline')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Dibuat')
                                    ->dateTime('d M Y H:i'),
                                
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Terakhir Diupdate')
                                    ->dateTime('d M Y H:i'),
                            ]),
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
        // Filter payment methods by users that have access to current tenant
        // Payment methods belong to users, and users belong to tenants via user_tenant_access
        return $query
            ->whereHas('user', function (Builder $userQuery) use ($tenantId) {
                $userQuery->whereHas('tenants', function (Builder $tenantQuery) use ($tenantId) {
                    $tenantQuery->where('tenants.id', $tenantId);
                });
            })
            ->with(['user', 'payments']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentMethods::route('/'),
            'view' => Pages\ViewPaymentMethod::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        // Payment methods untuk billing SaaS biasanya dibuat via gateway/hosted portal
        // atau sync dari payment provider, bukan manual CRUD
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Payment methods tidak boleh dihapus manual
        // Harus dihapus via gateway/payment provider
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Payment methods read-only, tidak boleh diubah manual
        // Update harus dilakukan via gateway/payment provider
        return false;
    }

    /**
     * Hide from navigation menu (commented out, not deleted)
     * To show again, change return value to true or remove this method
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden from navigation menu
    }
}

