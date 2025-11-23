<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
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

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Tagihan';

    protected static ?int $navigationSort = 20;

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Detail Tagihan')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('Nomor Invoice')
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'paid' => 'Lunas',
                                'pending' => 'Menunggu',
                                'failed' => 'Gagal',
                                'refunded' => 'Dikembalikan',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('Jumlah')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('tax_amount')
                            ->label('Pajak')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Jatuh Tempo')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Dibayar Pada')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Nomor invoice disalin')
                    ->copyMessageDuration(1500),
                
                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label('Paket')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Lunas',
                        'pending' => 'Menunggu',
                        'failed' => 'Gagal',
                        'refunded' => 'Dikembalikan',
                        'cancelled' => 'Dibatalkan',
                        default => ucfirst($state),
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->total_amount ?? 0)))
                    ->sortable()
                    ->alignEnd(),
                
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (Invoice $record): ?string => 
                        $record->isOverdue() ? 'danger' : 
                        ($record->isPending() && $record->due_date->diffInDays() <= 7 ? 'warning' : null)
                    ),
                
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Lunas',
                        'pending' => 'Menunggu',
                        'failed' => 'Gagal',
                        'refunded' => 'Dikembalikan',
                        'cancelled' => 'Dibatalkan',
                    ]),
                
                Tables\Filters\Filter::make('overdue')
                    ->label('Terlambat')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                
                Tables\Filters\Filter::make('due_soon')
                    ->label('Jatuh Tempo Segera')
                    ->query(fn (Builder $query): Builder => $query->dueSoon()),
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
                Section::make('Informasi Tagihan')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('invoice_number')
                                    ->label('Nomor Invoice')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'failed' => 'danger',
                                        'refunded' => 'gray',
                                        'cancelled' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'paid' => 'Lunas',
                                        'pending' => 'Menunggu',
                                        'failed' => 'Gagal',
                                        'refunded' => 'Dikembalikan',
                                        'cancelled' => 'Dibatalkan',
                                        default => ucfirst($state),
                                    }),
                                
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Jumlah')
                                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0))),
                                
                                Infolists\Components\TextEntry::make('tax_amount')
                                    ->label('Pajak')
                                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->tax_amount ?? 0)))
                                    ->placeholder('Tidak ada pajak'),
                                
                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Total')
                                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->total_amount ?? 0))),
                                
                                Infolists\Components\TextEntry::make('due_date')
                                    ->label('Jatuh Tempo')
                                    ->date('d M Y')
                                    ->color(fn (Invoice $record): ?string => 
                                        $record->isOverdue() ? 'danger' : 
                                        ($record->isPending() && $record->due_date->diffInDays() <= 7 ? 'warning' : null)
                                    ),
                                
                                Infolists\Components\TextEntry::make('paid_at')
                                    ->label('Dibayar Pada')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Belum dibayar'),
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
            ->with(['subscription.plan', 'subscriptionPayments']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        // Invoice dibuat dari billing engine, bukan manual
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Invoice tidak boleh dihapus manual
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Invoice read-only, tidak boleh diubah
        return false;
    }
}

