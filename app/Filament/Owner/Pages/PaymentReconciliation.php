<?php

namespace App\Filament\Owner\Pages;

use App\Models\SubscriptionPayment;
use App\Services\StoreContext;
use App\Services\XenditService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use BackedEnum;
use App\Support\Currency;

class PaymentReconciliation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Rekonsiliasi Pembayaran';



    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan & Laporan';

    protected string $view = 'filament.owner.pages.payment-reconciliation';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('external_id')
                    ->label('Payment ID')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('xendit_invoice_id')
                    ->label('Xendit ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                
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
                
                Tables\Columns\TextColumn::make('gateway_fee')
                    ->label('Gateway Fee')
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? $record->gateway_fee ?? 0)))
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Net Amount')
                    ->getStateUsing(fn (SubscriptionPayment $record): float => 
                        $record->amount - $record->gateway_fee
                    )
                    ->formatStateUsing(fn($s, $record) => Currency::rupiah((float) ($s ?? 0)))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->formatStateUsing(fn ($record): string => 
                        $record->getPaymentMethodDisplayName()
                    )
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not paid'),
                
                Tables\Columns\IconColumn::make('reconciled')
                    ->label('Reconciled')
                    ->getStateUsing(fn (SubscriptionPayment $record): bool => 
                        $record->status === 'paid' && $record->gateway_response !== null
                    )
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                    ]),
                
                Tables\Filters\Filter::make('unreconciled')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('status', 'paid')
                              ->whereNull('gateway_response')
                    )
                    ->label('Unreconciled Payments'),
                
                Tables\Filters\Filter::make('this_month')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('created_at', '>=', now()->startOfMonth())
                    )
                    ->label('This Month'),
            ])
            ->actions([
                \Filament\Actions\Action::make('sync_status')
                    ->label('Sync Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function (SubscriptionPayment $record) {
                        $this->syncPaymentStatus($record);
                    })
                    ->visible(fn (SubscriptionPayment $record): bool => 
                        !empty($record->xendit_invoice_id)
                    ),
                
                \Filament\Actions\ViewAction::make()
                    ->url(fn (SubscriptionPayment $record): string => 
                        route('filament.owner.resources.subscription-payments.view', $record)
                    ),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('sync_selected')
                    ->label('Sync Selected')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function ($records) {
                        $this->syncMultiplePayments($records);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        $storeContext = app(StoreContext::class);
        
        return SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeContext) {
            $query->where('store_id', $storeContext->current(auth()->user()));
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeContext) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeContext) {
                $subQuery->where('id', $storeContext->current(auth()->user()));
            });
        })->with(['subscription.plan', 'landingSubscription']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_all_pending')
                ->label('Sync All Pending')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action('syncAllPendingPayments'),
            
            Actions\Action::make('export_reconciliation')
                ->label('Export Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action('exportReconciliationReport'),
        ];
    }

    public function syncPaymentStatus(SubscriptionPayment $payment): void
    {
        try {
            if (!$payment->xendit_invoice_id) {
                Notification::make()
                    ->title('Cannot sync payment')
                    ->body('No Xendit invoice ID found for this payment.')
                    ->warning()
                    ->send();
                return;
            }

            $xenditService = app(XenditService::class);
            $invoiceData = $xenditService->getInvoice($payment->xendit_invoice_id);
            
            if ($invoiceData) {
                $oldStatus = $payment->status;
                $payment->updateFromXenditCallback($invoiceData);
                
                Notification::make()
                    ->title('Payment status synced')
                    ->body("Status updated from '{$oldStatus}' to '{$payment->fresh()->status}'")
                    ->success()
                    ->send();
            } else {
                throw new \Exception('Failed to retrieve payment data from Xendit');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Sync failed')
                ->body('Unable to sync payment status: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncMultiplePayments($records): void
    {
        $successCount = 0;
        $failCount = 0;

        foreach ($records as $payment) {
            try {
                if ($payment->xendit_invoice_id) {
                    $xenditService = app(XenditService::class);
                    $invoiceData = $xenditService->getInvoice($payment->xendit_invoice_id);
                    
                    if ($invoiceData) {
                        $payment->updateFromXenditCallback($invoiceData);
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $failCount++;
            }
        }

        Notification::make()
            ->title('Bulk sync completed')
            ->body("Successfully synced {$successCount} payments. {$failCount} failed.")
            ->success()
            ->send();
    }

    public function syncAllPendingPayments(): void
    {
        $pendingPayments = $this->getTableQuery()
            ->where('status', 'pending')
            ->whereNotNull('xendit_invoice_id')
            ->get();

        if ($pendingPayments->isEmpty()) {
            Notification::make()
                ->title('No pending payments')
                ->body('No pending payments found to sync.')
                ->info()
                ->send();
            return;
        }

        $this->syncMultiplePayments($pendingPayments);
    }

    public function exportReconciliationReport(): void
    {
        $payments = $this->getTableQuery()
            ->where('created_at', '>=', now()->startOfMonth())
            ->orderBy('created_at', 'desc')
            ->get();

        $csvData = [];
        $csvData[] = [
            'Payment ID',
            'Xendit ID',
            'Amount',
            'Gateway Fee',
            'Net Amount',
            'Status',
            'Payment Method',
            'Created At',
            'Paid At',
            'Reconciled',
        ];

        foreach ($payments as $payment) {
            $csvData[] = [
                $payment->external_id,
                $payment->xendit_invoice_id ?? 'N/A',
                $payment->amount,
                $payment->gateway_fee,
                $payment->amount - $payment->gateway_fee,
                ucfirst($payment->status),
                $payment->getPaymentMethodDisplayName(),
                $payment->created_at->format('Y-m-d H:i:s'),
                $payment->paid_at?->format('Y-m-d H:i:s') ?? 'Not paid',
                ($payment->status === 'paid' && $payment->gateway_response !== null) ? 'Yes' : 'No',
            ];
        }

        $filename = 'payment_reconciliation_' . now()->format('Y_m_d_H_i_s') . '.csv';
        $handle = fopen('php://temp', 'w+');
        
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->send();
    }

    public static function canAccess(): bool
    {
        $storeContext = app(StoreContext::class);
        
        // Only show if store has subscription payments
        return SubscriptionPayment::whereHas('subscription', function (Builder $query) use ($storeContext) {
            $query->where('store_id', $storeContext->current(auth()->user()));
        })->orWhereHas('landingSubscription', function (Builder $query) use ($storeContext) {
            $query->whereHas('provisionedStore', function (Builder $subQuery) use ($storeContext) {
                $subQuery->where('id', $storeContext->current(auth()->user()));
            });
        })->exists();
    }
}