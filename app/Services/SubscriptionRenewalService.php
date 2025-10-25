<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Invoice;
use App\Services\XenditService;
use App\Services\SubscriptionActivationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class SubscriptionRenewalService
{
    protected XenditService $xenditService;
    protected SubscriptionActivationService $activationService;

    public function __construct(XenditService $xenditService, SubscriptionActivationService $activationService)
    {
        $this->xenditService = $xenditService;
        $this->activationService = $activationService;
    }

    /**
     * Process subscription renewals for subscriptions expiring soon.
     */
    public function processRenewals(): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        // Get subscriptions expiring in the next 7 days
        $expiringSubscriptions = $this->getExpiringSubscriptions();

        foreach ($expiringSubscriptions as $subscription) {
            try {
                $result = $this->processSubscriptionRenewal($subscription);
                $results['details'][] = $result;

                if ($result['success']) {
                    $results['processed']++;
                } else {
                    $results['failed']++;
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'subscription_id' => $subscription->id,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process subscription renewal', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Subscription renewal batch processed', $results);

        return $results;
    }

    /**
     * Get subscriptions that are expiring soon.
     */
    protected function getExpiringSubscriptions(): Collection
    {
        $reminderDays = config('xendit.renewal_reminder_days', 7);

        return Subscription::where('status', 'active')
            ->where('ends_at', '<=', now()->addDays($reminderDays))
            ->where('ends_at', '>', now())
            ->with(['store', 'plan'])
            ->get();
    }

    /**
     * Process renewal for a single subscription.
     */
    public function processSubscriptionRenewal(Subscription $subscription): array
    {
        // Check if renewal invoice already exists
        $existingInvoice = $this->getExistingRenewalInvoice($subscription);
        
        if ($existingInvoice) {
            return [
                'subscription_id' => $subscription->id,
                'success' => true,
                'action' => 'skipped',
                'reason' => 'Renewal invoice already exists',
                'invoice_id' => $existingInvoice->id,
            ];
        }

        // Create renewal invoice
        $invoice = $this->createRenewalInvoice($subscription);

        // Create subscription payment via Xendit
        $subscriptionPayment = $this->createRenewalPayment($subscription, $invoice);

        // Send renewal reminder (TODO: implement in email task)
        $this->sendRenewalReminder($subscription, $subscriptionPayment);

        return [
            'subscription_id' => $subscription->id,
            'success' => true,
            'action' => 'created',
            'invoice_id' => $invoice->id,
            'subscription_payment_id' => $subscriptionPayment->id,
            'xendit_invoice_id' => $subscriptionPayment->xendit_invoice_id,
            'payment_url' => $subscriptionPayment->gateway_response['invoice_url'] ?? null,
        ];
    }

    /**
     * Check if renewal invoice already exists for subscription.
     */
    protected function getExistingRenewalInvoice(Subscription $subscription): ?Invoice
    {
        return Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->where('due_date', '>=', now())
            ->first();
    }

    /**
     * Create renewal invoice for subscription.
     */
    protected function createRenewalInvoice(Subscription $subscription): Invoice
    {
        $nextPeriodStart = $subscription->ends_at;
        $nextPeriodEnd = $this->calculateNextPeriodEnd($subscription);

        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'amount' => $subscription->amount,
            'tax_amount' => 0, // TODO: Calculate tax if needed
            'total_amount' => $subscription->amount,
            'status' => 'pending',
            'due_date' => $subscription->ends_at->subDays(1), // Due 1 day before expiry
            'line_items' => [
                [
                    'description' => "Subscription renewal for {$subscription->plan->name}",
                    'period_start' => $nextPeriodStart->toDateString(),
                    'period_end' => $nextPeriodEnd->toDateString(),
                    'amount' => $subscription->amount,
                ]
            ],
            'metadata' => [
                'renewal' => true,
                'period_start' => $nextPeriodStart,
                'period_end' => $nextPeriodEnd,
            ],
        ]);

        Log::info('Renewal invoice created', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total_amount,
            'due_date' => $invoice->due_date,
        ]);

        return $invoice;
    }

    /**
     * Calculate next period end date based on billing cycle.
     */
    protected function calculateNextPeriodEnd(Subscription $subscription): \Carbon\Carbon
    {
        $start = $subscription->ends_at;

        return match($subscription->billing_cycle) {
            'monthly' => $start->copy()->addMonth(),
            'quarterly' => $start->copy()->addMonths(3),
            'yearly' => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }

    /**
     * Create subscription payment for renewal via Xendit.
     */
    protected function createRenewalPayment(Subscription $subscription, Invoice $invoice): SubscriptionPayment
    {
        $externalId = SubscriptionPayment::generateExternalId();
        
        // Create Xendit invoice
        $xenditInvoiceData = [
            'external_id' => $externalId,
            'amount' => $invoice->total_amount,
            'description' => "Subscription renewal for {$subscription->store->name}",
            'invoice_duration' => config('xendit.invoice_expiry_hours', 24) * 3600,
            'customer' => [
                'given_names' => $subscription->store->owner->name ?? 'Store Owner',
                'email' => $subscription->store->email,
            ],
            'success_redirect_url' => url('/subscription/renewal/success'),
            'failure_redirect_url' => url('/subscription/renewal/failed'),
            'currency' => config('xendit.currency', 'IDR'),
            'payment_methods' => ['bank_transfer', 'e_wallet', 'qris'],
        ];

        $xenditResponse = $this->xenditService->createInvoice($xenditInvoiceData);

        // Create subscription payment record
        $subscriptionPayment = SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'xendit_invoice_id' => $xenditResponse['id'],
            'external_id' => $externalId,
            'payment_method' => 'bank_transfer', // Default, will be updated by webhook
            'amount' => $invoice->total_amount,
            'status' => 'pending',
            'expires_at' => now()->addHours(config('xendit.invoice_expiry_hours', 24)),
            'gateway_response' => $xenditResponse,
        ]);

        Log::info('Renewal payment created', [
            'subscription_id' => $subscription->id,
            'subscription_payment_id' => $subscriptionPayment->id,
            'xendit_invoice_id' => $xenditResponse['id'],
            'amount' => $subscriptionPayment->amount,
        ]);

        return $subscriptionPayment;
    }

    /**
     * Send renewal reminder to customer.
     */
    protected function sendRenewalReminder(Subscription $subscription, SubscriptionPayment $subscriptionPayment): void
    {
        // TODO: Implement email sending in task 8
        Log::info('Renewal reminder should be sent', [
            'subscription_id' => $subscription->id,
            'email' => $subscription->store->email,
            'payment_url' => $subscriptionPayment->gateway_response['invoice_url'] ?? null,
        ]);
    }

    /**
     * Handle successful renewal payment.
     */
    public function handleRenewalPayment(SubscriptionPayment $subscriptionPayment): void
    {
        if (!$subscriptionPayment->isPaid()) {
            throw new \Exception('Cannot process renewal for unpaid payment');
        }

        $subscription = $subscriptionPayment->subscription;
        
        if (!$subscription) {
            throw new \Exception('Subscription not found for renewal payment');
        }

        // Extend subscription period
        $newEndDate = $this->calculateNextPeriodEnd($subscription);
        
        $subscription->update([
            'status' => 'active',
            'ends_at' => $newEndDate,
        ]);

        // Mark invoice as paid
        if ($subscriptionPayment->invoice) {
            $subscriptionPayment->invoice->markAsPaid();
        }

        Log::info('Subscription renewed successfully', [
            'subscription_id' => $subscription->id,
            'subscription_payment_id' => $subscriptionPayment->id,
            'old_end_date' => $subscription->getOriginal('ends_at'),
            'new_end_date' => $newEndDate,
        ]);
    }

    /**
     * Handle failed renewal payments and retry logic.
     */
    public function handleFailedRenewal(SubscriptionPayment $subscriptionPayment): void
    {
        $subscription = $subscriptionPayment->subscription;
        
        if (!$subscription) {
            return;
        }

        // Check retry count
        $retryCount = SubscriptionPayment::where('subscription_id', $subscription->id)
            ->where('invoice_id', $subscriptionPayment->invoice_id)
            ->where('status', 'failed')
            ->count();

        $maxRetries = config('xendit.max_renewal_retries', 3);

        if ($retryCount < $maxRetries) {
            // Create retry payment after 24 hours
            $this->scheduleRenewalRetry($subscription, $subscriptionPayment->invoice);
            
            Log::info('Renewal retry scheduled', [
                'subscription_id' => $subscription->id,
                'retry_count' => $retryCount + 1,
                'max_retries' => $maxRetries,
            ]);
        } else {
            // Max retries reached, handle grace period
            $this->activationService->handleGracePeriod($subscription);
            
            Log::warning('Max renewal retries reached', [
                'subscription_id' => $subscription->id,
                'retry_count' => $retryCount,
            ]);
        }
    }

    /**
     * Schedule renewal retry (TODO: implement with job queue).
     */
    protected function scheduleRenewalRetry(Subscription $subscription, Invoice $invoice): void
    {
        // TODO: Implement with Laravel Jobs/Queue system
        Log::info('Renewal retry should be scheduled', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Get renewal statistics.
     */
    public function getRenewalStats(): array
    {
        $today = now()->startOfDay();
        
        return [
            'expiring_today' => Subscription::where('status', 'active')
                ->whereBetween('ends_at', [$today, $today->copy()->endOfDay()])
                ->count(),
            
            'expiring_this_week' => Subscription::where('status', 'active')
                ->whereBetween('ends_at', [$today, $today->copy()->addWeek()])
                ->count(),
            
            'pending_renewals' => SubscriptionPayment::where('status', 'pending')
                ->whereNotNull('subscription_id')
                ->count(),
            
            'failed_renewals_today' => SubscriptionPayment::where('status', 'failed')
                ->whereNotNull('subscription_id')
                ->whereDate('created_at', $today)
                ->count(),
        ];
    }
}