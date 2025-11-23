<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\SubscriptionUsageResource\Pages;
use App\Models\SubscriptionUsage;
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

class SubscriptionUsageResource extends Resource
{
    protected static ?string $model = SubscriptionUsage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Pemakaian Fitur';

    protected static ?int $navigationSort = 40;

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Detail Pemakaian Fitur')
                    ->schema([
                        Forms\Components\TextInput::make('feature_type')
                            ->label('Tipe Fitur')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('current_usage')
                            ->label('Pemakaian Saat Ini')
                            ->numeric()
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('annual_quota')
                            ->label('Quota Tahunan')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => $state === null ? 'Unlimited' : number_format($state))
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('usage_percentage')
                            ->label('Persentase')
                            ->formatStateUsing(fn ($record) => $record ? $record->getUsagePercentage() . '%' : '0%')
                            ->disabled(),
                        
                        Forms\Components\Checkbox::make('soft_cap_triggered')
                            ->label('Soft Cap Terpicu')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('soft_cap_triggered_at')
                            ->label('Soft Cap Terpicu Pada')
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('subscription_year_start')
                            ->label('Tahun Mulai')
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('subscription_year_end')
                            ->label('Tahun Berakhir')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label('Paket')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('feature_type')
                    ->label('Tipe Fitur')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'transactions' => 'Transaksi',
                        'products' => 'Produk',
                        'users' => 'Pengguna',
                        'categories' => 'Kategori',
                        'stores' => 'Toko',
                        'staff' => 'Staff',
                        'devices' => 'Device',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    }),
                
                Tables\Columns\TextColumn::make('current_usage')
                    ->label('Pemakaian')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('annual_quota')
                    ->label('Quota')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state === null ? 'Unlimited' : number_format($state))
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('usage_percentage')
                    ->label('Persentase')
                    ->getStateUsing(fn ($record) => $record->getUsagePercentage() . '%')
                    ->badge()
                    ->color(fn ($record) => 
                        $record->getUsagePercentage() >= 100 ? 'danger' :
                        ($record->getUsagePercentage() >= 80 ? 'warning' : 'success')
                    )
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw("(current_usage / NULLIF(annual_quota, 0) * 100) {$direction}");
                    }),
                
                Tables\Columns\IconColumn::make('soft_cap_triggered')
                    ->label('Soft Cap')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),
                
                Tables\Columns\TextColumn::make('subscription_year_start')
                    ->label('Tahun Mulai')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('subscription_year_end')
                    ->label('Tahun Berakhir')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diupdate')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('feature_type')
                    ->label('Tipe Fitur')
                    ->options([
                        'transactions' => 'Transaksi',
                        'products' => 'Produk',
                        'users' => 'Pengguna',
                        'categories' => 'Kategori',
                        'stores' => 'Toko',
                        'staff' => 'Staff',
                        'devices' => 'Device',
                    ]),
                
                Tables\Filters\TernaryFilter::make('soft_cap_triggered')
                    ->label('Soft Cap Terpicu')
                    ->placeholder('Semua')
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak'),
                
                Tables\Filters\Filter::make('over_quota')
                    ->label('Melebihi Quota')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereColumn('current_usage', '>=', 'annual_quota')
                              ->whereNotNull('annual_quota')
                    ),
                
                Tables\Filters\Filter::make('near_quota')
                    ->label('Mendekati Quota (≥80%)')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereRaw('(current_usage / NULLIF(annual_quota, 0) * 100) >= 80')
                              ->whereNotNull('annual_quota')
                    ),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->label('Lihat'),
            ])
            ->defaultSort('feature_type', 'asc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pemakaian Fitur')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('feature_type')
                                    ->label('Tipe Fitur')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'transactions' => 'Transaksi',
                                        'products' => 'Produk',
                                        'users' => 'Pengguna',
                                        'categories' => 'Kategori',
                                        'stores' => 'Toko',
                                        'staff' => 'Staff',
                                        'devices' => 'Device',
                                        default => ucfirst(str_replace('_', ' ', $state)),
                                    }),
                                
                                Infolists\Components\TextEntry::make('current_usage')
                                    ->label('Pemakaian Saat Ini')
                                    ->numeric(),
                                
                                Infolists\Components\TextEntry::make('annual_quota')
                                    ->label('Quota Tahunan')
                                    ->formatStateUsing(fn ($state) => $state === null ? 'Unlimited' : number_format($state)),
                                
                                Infolists\Components\TextEntry::make('usage_percentage')
                                    ->label('Persentase')
                                    ->getStateUsing(fn ($record) => $record->getUsagePercentage() . '%')
                                    ->badge()
                                    ->color(fn ($record) => 
                                        $record->getUsagePercentage() >= 100 ? 'danger' :
                                        ($record->getUsagePercentage() >= 80 ? 'warning' : 'success')
                                    ),
                                
                                Infolists\Components\IconEntry::make('soft_cap_triggered')
                                    ->label('Soft Cap Terpicu')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('warning')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),
                                
                                Infolists\Components\TextEntry::make('soft_cap_triggered_at')
                                    ->label('Soft Cap Terpicu Pada')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Belum terpicu'),
                                
                                Infolists\Components\TextEntry::make('subscription_year_start')
                                    ->label('Tahun Mulai')
                                    ->date('d M Y'),
                                
                                Infolists\Components\TextEntry::make('subscription_year_end')
                                    ->label('Tahun Berakhir')
                                    ->date('d M Y'),
                            ]),
                    ]),
                
                Section::make('Informasi Subscription')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('subscription.plan.name')
                                    ->label('Paket'),
                                
                                Infolists\Components\TextEntry::make('subscription.billing_cycle')
                                    ->label('Siklus Penagihan')
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state === 'monthly' ? 'Bulanan' : 
                                        ($state === 'yearly' ? 'Tahunan' : ($state ? ucfirst($state) : 'N/A'))
                                    ),
                                
                                Infolists\Components\TextEntry::make('subscription.starts_at')
                                    ->label('Mulai Subscription')
                                    ->date('d M Y'),
                                
                                Infolists\Components\TextEntry::make('subscription.ends_at')
                                    ->label('Berakhir Subscription')
                                    ->date('d M Y'),
                            ]),
                    ])
                    ->visible(fn ($record): bool => $record->subscription !== null)
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
        $query = parent::getEloquentQuery();
        
        // Get current tenant ID using GlobalFilterService or fallback to user's current tenant
        $globalFilter = app(GlobalFilterService::class);
        $tenantId = $globalFilter->getCurrentTenantId();
        
        if (!$tenantId) {
            // Fallback to user's current tenant
            $tenantId = auth()->user()?->currentTenant()?->id;
        }
        
        if (!$tenantId) {
            return $query->whereRaw('1 = 0'); // Return empty query
        }
        
        return $query
            ->whereHas('subscription', function (Builder $subQuery) use ($tenantId) {
                $subQuery->where('tenant_id', $tenantId);
            })
            ->with(['subscription.plan']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionUsage::route('/'),
            'view' => Pages\ViewSubscriptionUsage::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        // Usage tracking dibuat otomatis dari billing engine, bukan manual
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Usage record tidak boleh dihapus manual
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Usage record read-only, tidak boleh diubah manual
        return false;
    }
}

