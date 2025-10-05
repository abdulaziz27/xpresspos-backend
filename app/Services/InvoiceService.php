<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * Create an invoice for a subscription.
     */
    public function createInvoice(Subscription $subscription, array $options = []): Invoice
    {
        try {
            return DB::transaction(function () use ($subscription, $options) {
                $amount = $options['amount'] ?? $subscription->amount;
                $taxAmount = $options['tax_amount'] ?? 0;
                $dueDate = $options['due_date'] ?? now()->addDays(7);
                $lineItems = $options['line_items'] ?? $this->generateLineItems($subscription, $amount);
                $metadata = $options['metadata'] ?? [];

                $invoice = $subscription->invoices()->create([
                    'amount' => $amount,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $amount + $taxAmount,
                    'status' => 'pending',
                    'due_date' => $dueDate,
                    'line_items' => $lineItems,
                    'metadata' => $metadata,
                ]);

                Log::info('Invoice created', [
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,
                    'total_amount' => $invoice->total_amount,
                ]);

                return $invoice;
            });
        } catch (\Exception $e) {
            Log::error('Failed to create invoice', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create invoice: ' . $e->getMessage());
        }
    }

    /**
     * Create initial invoice for new subscription.
     */
    public function createInitialInvoice(Subscription $subscription): Invoice
    {
        $options = [
            'amount' => $subscription->amount,
            'tax_amount' => $this->calculateTax($subscription->amount),
            'due_date' => $subscription->starts_at,
            'metadata' => [
                'type' => 'initial',
                'billing_cycle' => $subscription->billing_cycle,
                'plan_name' => $subscription->plan->name,
            ],
        ];

        return $this->createInvoice($subscription, $options);
    }

    /**
     * Create renewal invoice for subscription.
     */
    public function createRenewalInvoice(Subscription $subscription): Invoice
    {
        $options = [
            'amount' => $subscription->amount,
            'tax_amount' => $this->calculateTax($subscription->amount),
            'due_date' => $subscription->ends_at,
            'metadata' => [
                'type' => 'renewal',
                'billing_cycle' => $subscription->billing_cycle,
                'plan_name' => $subscription->plan->name,
                'renewal_date' => now(),
            ],
        ];

        return $this->createInvoice($subscription, $options);
    }

    /**
     * Create upgrade invoice with prorated amount.
     */
    public function createUpgradeInvoice(Subscription $subscription, float $proratedAmount, array $upgradeDetails = []): Invoice
    {
        $options = [
            'amount' => $proratedAmount,
            'tax_amount' => $this->calculateTax($proratedAmount),
            'due_date' => now()->addDays(7),
            'metadata' => array_merge([
                'type' => 'upgrade',
                'billing_cycle' => $subscription->billing_cycle,
                'plan_name' => $subscription->plan->name,
                'upgrade_date' => now(),
            ], $upgradeDetails),
        ];

        return $this->createInvoice($subscription, $options);
    }

    /**
     * Get pending invoices for a subscription.
     */
    public function getPendingInvoices(Subscription $subscription): \Illuminate\Database\Eloquent\Collection
    {
        return $subscription->invoices()
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get overdue invoices for a subscription.
     */
    public function getOverdueInvoices(Subscription $subscription): \Illuminate\Database\Eloquent\Collection
    {
        return $subscription->invoices()
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Mark invoice as paid and update subscription.
     */
    public function markInvoiceAsPaid(Invoice $invoice): void
    {
        try {
            DB::transaction(function () use ($invoice) {
                $invoice->markAsPaid();

                // If this is a renewal invoice, extend the subscription
                if ($invoice->metadata['type'] ?? '' === 'renewal') {
                    $this->extendSubscriptionForPaidInvoice($invoice);
                }

                Log::info('Invoice marked as paid', [
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $invoice->subscription_id,
                    'amount' => $invoice->total_amount,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to mark invoice as paid', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to mark invoice as paid: ' . $e->getMessage());
        }
    }

    /**
     * Generate line items for invoice.
     */
    private function generateLineItems(Subscription $subscription, float $amount): array
    {
        $plan = $subscription->plan;
        $billingCycle = $subscription->billing_cycle;

        $lineItems = [
            [
                'id' => 'subscription',
                'name' => "{$plan->name} Plan ({$billingCycle})",
                'description' => "Subscription for {$plan->name} plan, {$billingCycle} billing",
                'quantity' => 1,
                'unit_price' => $amount,
                'total_price' => $amount,
            ],
        ];

        return $lineItems;
    }

    /**
     * Calculate tax amount (11% VAT for Indonesia).
     */
    private function calculateTax(float $amount): float
    {
        return round($amount * 0.11, 2);
    }

    /**
     * Extend subscription when renewal invoice is paid.
     */
    private function extendSubscriptionForPaidInvoice(Invoice $invoice): void
    {
        $subscription = $invoice->subscription;

        $newEndsAt = $subscription->billing_cycle === 'annual'
            ? $subscription->ends_at->addYear()
            : $subscription->ends_at->addMonth();

        $subscription->update([
            'ends_at' => $newEndsAt,
            'status' => 'active',
        ]);

        Log::info('Subscription extended after invoice payment', [
            'subscription_id' => $subscription->id,
            'new_ends_at' => $newEndsAt,
            'invoice_id' => $invoice->id,
        ]);
    }
}
