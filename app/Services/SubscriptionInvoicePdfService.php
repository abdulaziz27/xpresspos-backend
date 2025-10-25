<?php

namespace App\Services;

use App\Models\SubscriptionPayment;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class SubscriptionInvoicePdfService
{
    /**
     * Generate PDF invoice for subscription payment.
     */
    public function generateInvoicePdf(SubscriptionPayment $subscriptionPayment): ?string
    {
        try {
            // Get or create invoice
            $invoice = $this->getOrCreateInvoice($subscriptionPayment);
            
            // Generate PDF content
            $pdfContent = $this->generatePdfContent($subscriptionPayment, $invoice);
            
            // Save PDF to storage
            $pdfPath = $this->savePdfToStorage($subscriptionPayment, $pdfContent);
            
            return $pdfPath;
        } catch (\Exception $e) {
            \Log::error('Failed to generate subscription invoice PDF', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Get existing invoice or create new one for subscription payment.
     */
    private function getOrCreateInvoice(SubscriptionPayment $subscriptionPayment): Invoice
    {
        if ($subscriptionPayment->invoice) {
            return $subscriptionPayment->invoice;
        }

        // Create new invoice
        $invoice = Invoice::create([
            'subscription_id' => $subscriptionPayment->subscription_id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'amount' => $subscriptionPayment->amount,
            'tax_amount' => 0, // Configure as needed
            'total_amount' => $subscriptionPayment->amount,
            'currency' => 'IDR',
            'status' => $subscriptionPayment->isPaid() ? 'paid' : 'pending',
            'issued_at' => now(),
            'due_at' => $subscriptionPayment->expires_at ?? now()->addDays(7),
            'paid_at' => $subscriptionPayment->paid_at,
            'metadata' => [
                'subscription_payment_id' => $subscriptionPayment->id,
                'xendit_invoice_id' => $subscriptionPayment->xendit_invoice_id,
                'payment_method' => $subscriptionPayment->payment_method,
            ],
        ]);

        // Link invoice to subscription payment
        $subscriptionPayment->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }

    /**
     * Generate PDF content using view template.
     */
    private function generatePdfContent(SubscriptionPayment $subscriptionPayment, Invoice $invoice): string
    {
        $data = [
            'subscriptionPayment' => $subscriptionPayment,
            'invoice' => $invoice,
            'customerName' => $this->getCustomerName($subscriptionPayment),
            'customerEmail' => $this->getCustomerEmail($subscriptionPayment),
            'customerAddress' => $this->getCustomerAddress($subscriptionPayment),
            'planName' => $this->getPlanName($subscriptionPayment),
            'companyInfo' => $this->getCompanyInfo(),
            'generatedAt' => now(),
        ];

        $html = View::make('invoices.subscription-payment', $data)->render();
        
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        return $pdf->output();
    }

    /**
     * Save PDF content to storage and return path.
     */
    private function savePdfToStorage(SubscriptionPayment $subscriptionPayment, string $pdfContent): string
    {
        $fileName = sprintf(
            'subscription-invoices/%s/invoice_%s_%s.pdf',
            $subscriptionPayment->created_at->format('Y/m'),
            $subscriptionPayment->id,
            now()->format('YmdHis')
        );

        Storage::put($fileName, $pdfContent);

        return $fileName;
    }

    /**
     * Generate unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-SUB';
        $date = now()->format('Ymd');
        $sequence = str_pad(Invoice::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$date}-{$sequence}";
    }

    /**
     * Get customer name from subscription payment.
     */
    private function getCustomerName(SubscriptionPayment $subscriptionPayment): string
    {
        if ($subscriptionPayment->landingSubscription) {
            return $subscriptionPayment->landingSubscription->name;
        }

        if ($subscriptionPayment->subscription?->store) {
            return $subscriptionPayment->subscription->store->name;
        }

        return 'Valued Customer';
    }

    /**
     * Get customer email from subscription payment.
     */
    private function getCustomerEmail(SubscriptionPayment $subscriptionPayment): string
    {
        if ($subscriptionPayment->landingSubscription) {
            return $subscriptionPayment->landingSubscription->email;
        }

        if ($subscriptionPayment->subscription?->store) {
            return $subscriptionPayment->subscription->store->email ?? '';
        }

        return '';
    }

    /**
     * Get customer address from subscription payment.
     */
    private function getCustomerAddress(SubscriptionPayment $subscriptionPayment): array
    {
        $address = [
            'company' => '',
            'address' => '',
            'city' => '',
            'country' => '',
            'phone' => '',
        ];

        if ($subscriptionPayment->landingSubscription) {
            $landing = $subscriptionPayment->landingSubscription;
            $address['company'] = $landing->company ?? '';
            $address['country'] = $landing->country ?? '';
            $address['phone'] = $landing->phone ?? '';
        }

        if ($subscriptionPayment->subscription?->store) {
            $store = $subscriptionPayment->subscription->store;
            $address['company'] = $store->name;
            $address['address'] = $store->address ?? '';
            $address['city'] = $store->city ?? '';
            $address['phone'] = $store->phone ?? '';
        }

        return $address;
    }

    /**
     * Get plan name from subscription payment.
     */
    private function getPlanName(SubscriptionPayment $subscriptionPayment): string
    {
        if ($subscriptionPayment->subscription?->plan) {
            return $subscriptionPayment->subscription->plan->name;
        }

        if ($subscriptionPayment->landingSubscription) {
            return ucfirst($subscriptionPayment->landingSubscription->plan) . ' Plan';
        }

        return 'Subscription Plan';
    }

    /**
     * Get company information for invoice header.
     */
    private function getCompanyInfo(): array
    {
        return [
            'name' => 'XpressPOS',
            'address' => 'Jl. Teknologi No. 123',
            'city' => 'Jakarta 12345',
            'country' => 'Indonesia',
            'phone' => '+62-21-XXXX-XXXX',
            'email' => 'billing@xpresspos.com',
            'website' => 'www.xpresspos.com',
            'tax_id' => 'NPWP: XX.XXX.XXX.X-XXX.XXX',
        ];
    }

    /**
     * Check if PDF exists for subscription payment.
     */
    public function pdfExists(SubscriptionPayment $subscriptionPayment): bool
    {
        $pattern = "subscription-invoices/{$subscriptionPayment->created_at->format('Y/m')}/invoice_{$subscriptionPayment->id}_*.pdf";
        $files = Storage::files(dirname($pattern));
        
        foreach ($files as $file) {
            if (str_contains($file, "invoice_{$subscriptionPayment->id}_")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get existing PDF path for subscription payment.
     */
    public function getExistingPdfPath(SubscriptionPayment $subscriptionPayment): ?string
    {
        $pattern = "subscription-invoices/{$subscriptionPayment->created_at->format('Y/m')}/invoice_{$subscriptionPayment->id}_*.pdf";
        $files = Storage::files(dirname($pattern));
        
        foreach ($files as $file) {
            if (str_contains($file, "invoice_{$subscriptionPayment->id}_")) {
                return $file;
            }
        }

        return null;
    }
}