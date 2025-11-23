<?php

namespace App\Filament\Owner\Resources\SubscriptionPaymentResource\Pages;

use App\Filament\Owner\Resources\SubscriptionPaymentResource;
use App\Models\SubscriptionPayment;
use App\Services\XenditService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ViewSubscriptionPayment extends ViewRecord
{
    protected static string $resource = SubscriptionPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_invoice')
                ->label('Unduh Invoice')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action('downloadInvoice')
                ->visible(fn (): bool => $this->record->status === 'paid'),
            
            Actions\Action::make('retry_payment')
                ->label('Retry Payment')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action('retryPayment')
                ->visible(fn (): bool => 
                    in_array($this->record->status, ['failed', 'expired'])
                ),
            
            Actions\Action::make('check_status')
                ->label('Check Payment Status')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action('checkPaymentStatus'),
            
            Actions\Action::make('view_subscription')
                ->label('View Subscription')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url(fn (): string => 
                    $this->record->subscription 
                        ? route('filament.owner.resources.subscriptions.view', $this->record->subscription)
                        : '#'
                )
                ->visible(fn (): bool => $this->record->subscription !== null),
        ];
    }

    public function downloadInvoice()
    {
        try {
            $invoicePdfService = app(\App\Services\SubscriptionInvoicePdfService::class);
            
            // Try to get existing PDF first
            $pdfPath = $invoicePdfService->getExistingPdfPath($this->record);

            // If not exists, generate new one
            if (!$pdfPath) {
                $pdfPath = $invoicePdfService->generateInvoicePdf($this->record);
            }

            if (!$pdfPath) {
                Notification::make()
                    ->title('Invoice tidak tersedia')
                    ->body('Tidak dapat menghasilkan PDF invoice. Silakan coba lagi atau hubungi support.')
                    ->danger()
                    ->send();
                return;
            }

            if (!Storage::exists($pdfPath)) {
                Notification::make()
                    ->title('File invoice tidak ditemukan')
                    ->body('File PDF invoice tidak ditemukan di storage. Silakan coba generate ulang.')
                    ->danger()
                    ->send();
                return;
            }

            $fullPath = Storage::path($pdfPath);
            $fileName = "Invoice_{$this->record->external_id}.pdf";

            return response()->download($fullPath, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to download invoice', [
                'subscription_payment_id' => $this->record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Error saat mengunduh invoice')
                ->body('Terjadi kesalahan saat mengunduh invoice: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function retryPayment(): void
    {
        try {
            $xenditService = app(XenditService::class);
            
            // Create new invoice for retry
            $invoiceData = $xenditService->createInvoice([
                'external_id' => 'RETRY-' . $this->record->external_id . '-' . now()->timestamp,
                'amount' => $this->record->amount,
                'description' => "Payment Retry - " . ($this->record->subscription?->plan?->name ?? 'Subscription'),
                'invoice_duration' => 86400, // 24 hours
                'customer' => [
                    'given_names' => ($this->record->subscription?->store?->name ?? 'Customer'),
                    'email' => ($this->record->subscription?->store?->email ?? auth()->user()->email),
                ],
                'success_redirect_url' => route('filament.owner.resources.subscription-payments.view', $this->record),
                'failure_redirect_url' => route('filament.owner.resources.subscription-payments.view', $this->record),
            ]);

            if ($invoiceData) {
                Notification::make()
                    ->title('Retry payment created')
                    ->body('You will be redirected to complete the payment.')
                    ->success()
                    ->send();

                // Redirect to Xendit payment page
                redirect($invoiceData['invoice_url']);
            } else {
                throw new \Exception('Failed to create retry payment');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Retry failed')
                ->body('Unable to create retry payment. Please try again or contact support.')
                ->danger()
                ->send();
        }
    }

    public function checkPaymentStatus(): void
    {
        try {
            $xenditService = app(XenditService::class);
            
            if (!$this->record->xendit_invoice_id) {
                Notification::make()
                    ->title('No Xendit invoice ID')
                    ->body('Cannot check status without Xendit invoice ID.')
                    ->warning()
                    ->send();
                return;
            }

            $invoiceData = $xenditService->getInvoice($this->record->xendit_invoice_id);
            
            if ($invoiceData) {
                // Update payment with latest data
                $this->record->updateFromXenditCallback($invoiceData);
                
                Notification::make()
                    ->title('Payment status updated')
                    ->body("Current status: " . ucfirst($this->record->fresh()->status))
                    ->success()
                    ->send();

                // Refresh the page to show updated data
                $this->redirect(request()->url());
            } else {
                throw new \Exception('Failed to retrieve payment status from Xendit');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Status check failed')
                ->body('Unable to check payment status. Please try again later.')
                ->danger()
                ->send();
        }
    }
}