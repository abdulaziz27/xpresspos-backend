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
            // Reload to ensure we have latest data
            $subscriptionPayment->refresh();
            
            // Get or create invoice (will return null if subscription_id is missing and can't create)
            $invoice = $this->getOrCreateInvoice($subscriptionPayment);
            
            // If invoice creation failed, create a temporary invoice object for PDF generation
            if (!$invoice) {
                \Log::warning('Could not create invoice in database, creating temporary invoice for PDF', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'subscription_id' => $subscriptionPayment->subscription_id,
                ]);
                
                // Create temporary invoice object (not saved to database)
                $invoice = new Invoice();
                $invoice->id = \Illuminate\Support\Str::uuid();
                $invoice->subscription_id = $subscriptionPayment->subscription_id;
                $invoice->invoice_number = $this->generateInvoiceNumber();
                $invoice->amount = (float) $subscriptionPayment->amount;
                $invoice->tax_amount = 0;
                $invoice->total_amount = (float) $subscriptionPayment->amount;
                $invoice->status = $subscriptionPayment->isPaid() ? 'paid' : 'pending';
                $invoice->due_date = $subscriptionPayment->expires_at 
                    ? $subscriptionPayment->expires_at->toDateString() 
                    : now()->addDays(7)->toDateString();
                $invoice->paid_at = $subscriptionPayment->paid_at;
                $invoice->line_items = [
                    [
                        'description' => $this->getPlanName($subscriptionPayment),
                        'quantity' => 1,
                        'unit_price' => (float) $subscriptionPayment->amount,
                        'total' => (float) $subscriptionPayment->amount,
                    ],
                ];
                $invoice->metadata = [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'xendit_invoice_id' => $subscriptionPayment->xendit_invoice_id,
                    'payment_method' => $subscriptionPayment->payment_method,
                    'landing_subscription_id' => $subscriptionPayment->landing_subscription_id,
                ];
                $invoice->created_at = now();
                $invoice->updated_at = now();
            }
            
            // Generate PDF content
            $pdfContent = $this->generatePdfContent($subscriptionPayment, $invoice);
            
            if (empty($pdfContent)) {
                \Log::error('Failed to generate PDF content', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
                return null;
            }
            
            // Save PDF to storage
            $pdfPath = $this->savePdfToStorage($subscriptionPayment, $pdfContent);
            
            if (!$pdfPath) {
                \Log::error('Failed to save PDF to storage', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
                return null;
            }
            
            return $pdfPath;
        } catch (\Exception $e) {
            \Log::error('Failed to generate subscription invoice PDF', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return null;
        }
    }

    /**
     * Get existing invoice or create new one for subscription payment.
     */
    private function getOrCreateInvoice(SubscriptionPayment $subscriptionPayment): ?Invoice
    {
        try {
            // Reload to ensure we have latest data
            $subscriptionPayment->refresh();
            
            if ($subscriptionPayment->invoice) {
                return $subscriptionPayment->invoice;
            }

            // Get subscription_id (could be null for landing subscriptions)
            $subscriptionId = $subscriptionPayment->subscription_id;
            
            // Check if subscription_id is required by database constraint
            // If subscription_id is null and required, we'll return null and create temp invoice in generateInvoicePdf
            if (!$subscriptionId) {
                \Log::info('Subscription payment has no subscription_id, will create temporary invoice', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'landing_subscription_id' => $subscriptionPayment->landing_subscription_id,
                ]);
                return null; // Will be handled in generateInvoicePdf
            }

            // Create new invoice
            $invoiceData = [
                'subscription_id' => $subscriptionId,
                'invoice_number' => $this->generateInvoiceNumber(),
                'amount' => (float) $subscriptionPayment->amount,
                'tax_amount' => 0,
                'total_amount' => (float) $subscriptionPayment->amount,
                'status' => $subscriptionPayment->isPaid() ? 'paid' : 'pending',
                'due_date' => $subscriptionPayment->expires_at 
                    ? $subscriptionPayment->expires_at->toDateString() 
                    : now()->addDays(7)->toDateString(),
                'paid_at' => $subscriptionPayment->paid_at,
                'line_items' => [
                    [
                        'description' => $this->getPlanName($subscriptionPayment),
                        'quantity' => 1,
                        'unit_price' => (float) $subscriptionPayment->amount,
                        'total' => (float) $subscriptionPayment->amount,
                    ],
                ],
                'metadata' => [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'xendit_invoice_id' => $subscriptionPayment->xendit_invoice_id,
                    'payment_method' => $subscriptionPayment->payment_method,
                    'landing_subscription_id' => $subscriptionPayment->landing_subscription_id,
                ],
            ];

            $invoice = Invoice::create($invoiceData);

            if (!$invoice) {
                \Log::error('Failed to create invoice', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'invoice_data' => $invoiceData,
                ]);
                return null;
            }

            // Link invoice to subscription payment
            try {
                $subscriptionPayment->update(['invoice_id' => $invoice->id]);
            } catch (\Exception $e) {
                \Log::warning('Failed to link invoice to subscription payment', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue anyway, invoice is created
            }

            return $invoice;
        } catch (\Exception $e) {
            \Log::error('Failed to get or create invoice', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return null;
        }
    }

    /**
     * Generate PDF content using view template.
     */
    private function generatePdfContent(SubscriptionPayment $subscriptionPayment, Invoice $invoice): ?string
    {
        try {
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

            // Check if view exists
            if (!View::exists('invoices.subscription-payment')) {
                \Log::error('Invoice view template not found', [
                    'view' => 'invoices.subscription-payment',
                ]);
                return null;
            }

            $html = View::make('invoices.subscription-payment', $data)->render();
            
            if (empty($html)) {
                \Log::error('Generated HTML is empty', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
                return null;
            }
            
            $pdf = Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'sans-serif',
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                ]);

            $output = $pdf->output();
            
            if (empty($output)) {
                \Log::error('PDF output is empty', [
                    'subscription_payment_id' => $subscriptionPayment->id,
                ]);
                return null;
            }

            return $output;
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF content', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return null;
        }
    }

    /**
     * Save PDF content to storage and return path.
     */
    private function savePdfToStorage(SubscriptionPayment $subscriptionPayment, string $pdfContent): ?string
    {
        try {
            $directory = sprintf(
                'subscription-invoices/%s',
                $subscriptionPayment->created_at->format('Y/m')
            );
            
            // Ensure directory exists
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }
            
            $fileName = sprintf(
                '%s/invoice_%s_%s.pdf',
                $directory,
                $subscriptionPayment->id,
                now()->format('YmdHis')
            );

            Storage::put($fileName, $pdfContent);

            return $fileName;
        } catch (\Exception $e) {
            \Log::error('Failed to save PDF to storage', [
                'subscription_payment_id' => $subscriptionPayment->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Generate unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        try {
            $prefix = 'INV-SUB';
            $date = now()->format('Ymd');
            
            // Get count of invoices created today
            $count = Invoice::whereDate('created_at', today())->count();
            $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            $invoiceNumber = "{$prefix}-{$date}-{$sequence}";
            
            // Ensure uniqueness
            $attempts = 0;
            while (Invoice::where('invoice_number', $invoiceNumber)->exists() && $attempts < 10) {
                $count++;
                $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                $invoiceNumber = "{$prefix}-{$date}-{$sequence}";
                $attempts++;
            }
            
            return $invoiceNumber;
        } catch (\Exception $e) {
            \Log::error('Failed to generate invoice number', [
                'error' => $e->getMessage(),
            ]);
            // Fallback to timestamp-based number
            return 'INV-SUB-' . now()->format('Ymd') . '-' . now()->format('His');
        }
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
        return $this->getExistingPdfPath($subscriptionPayment) !== null;
    }

    /**
     * Get existing PDF path for subscription payment.
     */
    public function getExistingPdfPath(SubscriptionPayment $subscriptionPayment): ?string
    {
        $directory = "subscription-invoices/{$subscriptionPayment->created_at->format('Y/m')}";
        
        // Check if directory exists
        if (!Storage::exists($directory)) {
            return null;
        }
        
        // Get all files in directory
        $files = Storage::files($directory);
        
        // Find file that matches the pattern
        foreach ($files as $file) {
            if (str_contains($file, "invoice_{$subscriptionPayment->id}_")) {
                return $file;
            }
        }

        return null;
    }
}