<?php

namespace App\Filament\Owner\Resources\SubscriptionResource\Pages;

use App\Filament\Owner\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\XenditService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_latest_invoice')
                ->label('Download Latest Invoice')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action('downloadLatestInvoice')
                ->visible(fn (): bool => $this->record->subscriptionPayments()->where('status', 'paid')->exists()),
            
            Actions\Action::make('view_payment_history')
                ->label('View Payment History')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(fn (): string => 
                    class_exists('App\Filament\Owner\Resources\SubscriptionPaymentResource') 
                        ? \App\Filament\Owner\Resources\SubscriptionPaymentResource::getUrl('index', ['subscription_id' => $this->record->id])
                        : '#'
                ),
            
            Actions\Action::make('renew_subscription')
                ->label('Renew Now')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action('renewSubscription')
                ->visible(fn (): bool => 
                    $this->record->status === 'active' && 
                    $this->record->ends_at->diffInDays() <= 30
                ),
            
            Actions\Action::make('reactivate_subscription')
                ->label('Reactivate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action('reactivateSubscription')
                ->visible(fn (): bool => $this->record->status === 'inactive'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Widget removed - using infolist "Ringkasan Langganan" section instead
        ];
    }

    public function downloadLatestInvoice(): void
    {
        $latestPayment = $this->record->subscriptionPayments()
            ->where('status', 'paid')
            ->latest()
            ->first();

        if (!$latestPayment) {
            Notification::make()
                ->title('No paid invoices found')
                ->warning()
                ->send();
            return;
        }

        // Try to find existing PDF
        $invoicePdfService = app(\App\Services\SubscriptionInvoicePdfService::class);
        $pdfPath = $invoicePdfService->getExistingPdfPath($latestPayment);

        if (!$pdfPath) {
            // Generate new PDF
            $pdfPath = $invoicePdfService->generateInvoicePdf($latestPayment);
        }

        if (!$pdfPath || !Storage::exists($pdfPath)) {
            Notification::make()
                ->title('Invoice PDF not available')
                ->body('Unable to generate or find the invoice PDF.')
                ->danger()
                ->send();
            return;
        }

        // Download the PDF
        Storage::download($pdfPath, "Invoice_{$latestPayment->id}.pdf");
    }

    public function renewSubscription(): void
    {
        try {
            $xenditService = app(XenditService::class);
            
            // Create renewal invoice
            $invoiceData = $xenditService->createInvoice([
                'external_id' => 'RENEWAL-' . $this->record->id . '-' . now()->timestamp,
                'amount' => $this->record->amount,
                'description' => "Subscription Renewal - {$this->record->plan->name}",
                'invoice_duration' => 86400, // 24 hours
                'customer' => [
                    'given_names' => $this->record->store->name,
                    'email' => $this->record->store->email ?? auth()->user()->email,
                ],
                'success_redirect_url' => route('filament.owner.resources.subscriptions.view', $this->record),
                'failure_redirect_url' => route('filament.owner.resources.subscriptions.view', $this->record),
            ]);

            if ($invoiceData) {
                Notification::make()
                    ->title('Renewal invoice created')
                    ->body('You will be redirected to complete the payment.')
                    ->success()
                    ->send();

                // Redirect to Xendit payment page
                redirect($invoiceData['invoice_url']);
            } else {
                throw new \Exception('Failed to create renewal invoice');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Renewal failed')
                ->body('Unable to create renewal invoice. Please try again or contact support.')
                ->danger()
                ->send();
        }
    }

    public function reactivateSubscription(): void
    {
        try {
            // Find the last failed payment
            $failedPayment = $this->record->subscriptionPayments()
                ->where('status', 'failed')
                ->latest()
                ->first();

            if ($failedPayment) {
                $xenditService = app(XenditService::class);
                
                // Create new invoice for reactivation
                $invoiceData = $xenditService->createInvoice([
                    'external_id' => 'REACTIVATE-' . $this->record->id . '-' . now()->timestamp,
                    'amount' => $failedPayment->amount,
                    'description' => "Subscription Reactivation - {$this->record->plan->name}",
                    'invoice_duration' => 86400, // 24 hours
                    'customer' => [
                        'given_names' => $this->record->store->name,
                        'email' => $this->record->store->email ?? auth()->user()->email,
                    ],
                    'success_redirect_url' => route('filament.owner.resources.subscriptions.view', $this->record),
                    'failure_redirect_url' => route('filament.owner.resources.subscriptions.view', $this->record),
                ]);

                if ($invoiceData) {
                    Notification::make()
                        ->title('Reactivation invoice created')
                        ->body('You will be redirected to complete the payment.')
                        ->success()
                        ->send();

                    // Redirect to Xendit payment page
                    redirect($invoiceData['invoice_url']);
                } else {
                    throw new \Exception('Failed to create reactivation invoice');
                }
            } else {
                throw new \Exception('No failed payment found for reactivation');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Reactivation failed')
                ->body('Unable to create reactivation invoice. Please contact support.')
                ->danger()
                ->send();
        }
    }
}