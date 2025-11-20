<?php

namespace App\Filament\Owner\Resources;

use App\Filament\Owner\Resources\SubscriptionPaymentResource\Pages;
use App\Models\SubscriptionPayment;
use App\Services\StoreContext;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;
use App\Support\Currency;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Riwayat Pembayaran';



    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Langganan & Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Detail Pembayaran')
                    ->schema([
                        Forms\Components\TextInput::make('external_id')
                            ->label('ID Pembayaran')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('xendit_invoice_id')
                            ->label('ID Invoice Xendit')
                            ->disabled(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Menunggu',
                                'paid' => 'Dibayar',
                                'failed' => 'Gagal',
                                'expired' => 'Kedaluwarsa',
                            ])
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('gateway_fee')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('payment_method')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('payment_channel')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('paid_at')
                            ->disabled(),
                        
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('external_id')
                    ->label('ID Pembayaran')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('ID pembayaran disalin')
                    ->copyMessageDuration(1500),
                
                Tables\Columns\TextColumn::make('subscription.plan.name')
                    ->label('Paket')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0)))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Metode')
                    ->formatStateUsing(fn ($record): string => 
                        $record->getPaymentMethodDisplayName()
                    )
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Pada')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Belum dibayar'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Menunggu',
                        'paid' => 'Dibayar',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                    ]),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'bank_transfer' => 'Transfer Bank',
                        'e_wallet' => 'Dompet Digital',
                        'qris' => 'QRIS',
                        'credit_card' => 'Kartu Kredit',
                    ]),
                
                Tables\Filters\Filter::make('paid_this_month')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('paid_at', '>=', now()->startOfMonth())
                              ->where('paid_at', '<=', now()->endOfMonth())
                    )
                    ->label('Dibayar Bulan Ini'),
                
                Tables\Filters\Filter::make('failed_payments')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'failed'))
                    ->label('Pembayaran Gagal'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make()->label('Lihat'),
                
                \Filament\Actions\Action::make('download_invoice')
                    ->label('Unduh Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function (SubscriptionPayment $record) {
                        return static::downloadInvoice($record);
                    })
                    ->visible(fn (SubscriptionPayment $record): bool => $record->status === 'paid'),
                
                \Filament\Actions\Action::make('retry_payment')
                    ->label('Coba Lagi Pembayaran')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (SubscriptionPayment $record) {
                        return static::retryPayment($record);
                    })
                    ->visible(fn (SubscriptionPayment $record): bool => 
                        in_array($record->status, ['failed', 'expired'])
                    ),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->visible(false), // Disable bulk delete for payment records
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('external_id')
                                    ->label('Payment ID')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('xendit_invoice_id')
                                    ->label('Xendit Invoice ID')
                                    ->copyable(),
                                
                Infolists\Components\TextEntry::make('amount')
                                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->amount ?? 0))),
                                
                                Infolists\Components\TextEntry::make('gateway_fee')
                                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->gateway_fee ?? 0)))
                                    ->placeholder('No fee'),
                                
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
                                    ->label('Payment Method')
                                    ->formatStateUsing(fn ($record): string => 
                                        $record->getPaymentMethodDisplayName()
                                    ),
                            ]),
                    ]),
                
                Section::make('Subscription Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('subscription.plan.name')
                                    ->label('Plan'),
                                
                                Infolists\Components\TextEntry::make('subscription.billing_cycle')
                                    ->label('Billing Cycle')
                                    ->formatStateUsing(fn (?string $state): string => 
                                        $state ? ucfirst($state) : 'N/A'
                                    ),
                                
                                Infolists\Components\TextEntry::make('subscription.starts_at')
                                    ->label('Subscription Start')
                                    ->date(),
                                
                                Infolists\Components\TextEntry::make('subscription.ends_at')
                                    ->label('Subscription End')
                                    ->date(),
                            ]),
                    ])
                    ->visible(fn ($record): bool => $record->subscription !== null),
                
                Section::make('Timeline')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Payment Created')
                                    ->dateTime(),
                                
                                Infolists\Components\TextEntry::make('paid_at')
                                    ->label('Paid At')
                                    ->dateTime()
                                    ->placeholder('Not paid'),
                                
                                Infolists\Components\TextEntry::make('expires_at')
                                    ->label('Expires At')
                                    ->dateTime()
                                    ->placeholder('No expiration'),
                                
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $storeContext = app(StoreContext::class);
        $storeId = $storeContext->current(auth()->user());
        $store = \App\Models\Store::find($storeId);
        
        if (!$store || !$store->tenant_id) {
            return parent::getEloquentQuery()->whereRaw('1 = 0'); // Return empty query
        }

        $tenant = $store->tenant;
        $tenantId = $tenant->id;

        return parent::getEloquentQuery()
            ->whereHas('subscription', function (Builder $query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->orWhereHas('landingSubscription', function (Builder $query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->with(['subscription.plan', 'landingSubscription']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPayments::route('/'),
            'view' => Pages\ViewSubscriptionPayment::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $storeContext = app(StoreContext::class);
        
        $pendingCount = static::getEloquentQuery()
            ->where('status', 'pending')
            ->count();
            
        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return true;
    }

    public static function downloadInvoice(SubscriptionPayment $payment)
    {
        $invoicePdfService = app(\App\Services\SubscriptionInvoicePdfService::class);
        $pdfPath = $invoicePdfService->getExistingPdfPath($payment);

        if (!$pdfPath) {
            $pdfPath = $invoicePdfService->generateInvoicePdf($payment);
        }

        if (!$pdfPath || !\Illuminate\Support\Facades\Storage::exists($pdfPath)) {
            \Filament\Notifications\Notification::make()
                ->title('Invoice not available')
                ->body('Unable to generate or find the invoice PDF.')
                ->danger()
                ->send();
            return;
        }

        return \Illuminate\Support\Facades\Storage::download($pdfPath, "Invoice_{$payment->external_id}.pdf");
    }

    public static function retryPayment(SubscriptionPayment $payment)
    {
        try {
            $xenditService = app(\App\Services\XenditService::class);
            
            // Create new invoice for retry
            $invoiceData = $xenditService->createInvoice([
                'external_id' => 'RETRY-' . $payment->external_id . '-' . now()->timestamp,
                'amount' => $payment->amount,
                'description' => "Payment Retry - " . ($payment->subscription?->plan?->name ?? 'Subscription'),
                'invoice_duration' => 86400, // 24 hours
                'customer' => [
                    'given_names' => ($payment->subscription?->store?->name ?? 'Customer'),
                    'email' => ($payment->subscription?->store?->email ?? auth()->user()->email),
                ],
                'success_redirect_url' => route('filament.owner.resources.subscription-payments.view', $payment),
                'failure_redirect_url' => route('filament.owner.resources.subscription-payments.view', $payment),
            ]);

            if ($invoiceData) {
                \Filament\Notifications\Notification::make()
                    ->title('Retry payment created')
                    ->body('You will be redirected to complete the payment.')
                    ->success()
                    ->send();

                // Redirect to Xendit payment page
                return redirect($invoiceData['invoice_url']);
            } else {
                throw new \Exception('Failed to create retry payment');
            }

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Retry failed')
                ->body('Unable to create retry payment. Please try again or contact support.')
                ->danger()
                ->send();
        }
    }
}