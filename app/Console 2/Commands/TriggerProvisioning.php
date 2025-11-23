<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LandingSubscription;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionProvisioningService;
use App\Services\XenditService;
use Illuminate\Support\Facades\Log;

class TriggerProvisioning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:provision 
                            {--email= : Email address of the user}
                            {--landing-subscription-id= : Landing subscription ID}
                            {--force : Force provisioning even if payment status is pending}
                            {--sync-xendit : Sync payment status from Xendit first}
                            {--mark-paid : Mark payment as paid before provisioning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually trigger subscription provisioning for paid landing subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');
        $landingSubscriptionId = $this->option('landing-subscription-id');
        $force = $this->option('force');
        $syncXendit = $this->option('sync-xendit');
        $markPaid = $this->option('mark-paid');

        // Find landing subscription
        if ($landingSubscriptionId) {
            $landingSubscription = LandingSubscription::find($landingSubscriptionId);
        } elseif ($email) {
            $landingSubscription = LandingSubscription::where('email', $email)
                ->orderBy('created_at', 'desc')
                ->first();
        } else {
            $this->error('Please provide either --email or --landing-subscription-id');
            return 1;
        }

        if (!$landingSubscription) {
            $this->error('Landing subscription not found');
            return 1;
        }

        $this->info("Found Landing Subscription ID: {$landingSubscription->id}");
        $this->line("Plan ID: {$landingSubscription->plan_id}");
        $this->line("Status: {$landingSubscription->status}");
        $this->line("Payment Status: {$landingSubscription->payment_status}");
        $this->newLine();

        // Check if already provisioned
        if ($landingSubscription->subscription_id) {
            $this->warn("⚠ This landing subscription is already provisioned!");
            $this->line("Subscription ID: {$landingSubscription->subscription_id}");
            
            if (!$this->confirm('Do you want to continue anyway?', false)) {
                return 0;
            }
        }

        // Find or create subscription payment
        $payment = $landingSubscription->latestSubscriptionPayment;
        
        if (!$payment && $landingSubscription->xendit_invoice_id) {
            // Try to find payment by xendit_invoice_id
            $payment = SubscriptionPayment::where('xendit_invoice_id', $landingSubscription->xendit_invoice_id)->first();
        }

        if (!$payment) {
            $this->error("No subscription payment found for this landing subscription.");
            $this->line("Xendit Invoice ID: " . ($landingSubscription->xendit_invoice_id ?? 'None'));
            
            if (!$landingSubscription->xendit_invoice_id) {
                $this->error("Cannot proceed without Xendit invoice ID.");
                return 1;
            }

            // Create payment record if we have xendit_invoice_id
            if ($this->confirm('Create payment record from Xendit invoice?', true)) {
                $payment = $this->createPaymentFromXendit($landingSubscription);
                if (!$payment) {
                    return 1;
                }
            } else {
                return 1;
            }
        }

        $this->info("Payment Record:");
        $this->line("  ID: {$payment->id}");
        $this->line("  Status: {$payment->status}");
        $this->line("  Amount: Rp " . number_format($payment->amount, 0, ',', '.'));
        $this->line("  Xendit Invoice: {$payment->xendit_invoice_id}");
        $this->newLine();

        // Sync from Xendit if requested
        if ($syncXendit && $payment->xendit_invoice_id) {
            $this->info("Syncing payment status from Xendit...");
            $this->syncPaymentFromXendit($payment);
            $payment->refresh();
            $this->line("Updated Status: {$payment->status}");
            $this->newLine();
        }

        // Mark as paid if requested
        if ($markPaid && !$payment->isPaid()) {
            $this->info("Marking payment as paid...");
            $payment->markAsPaid();
            $payment->refresh();
            
            // Also update landing subscription
            if ($landingSubscription->payment_status !== 'paid') {
                $landingSubscription->update([
                    'payment_status' => 'paid',
                    'status' => 'paid',
                    'stage' => 'payment_completed',
                    'paid_at' => now(),
                ]);
            }
            
            $this->line("✓ Payment marked as paid");
            $this->newLine();
        }

        // Check payment status
        // isPaid() requires both status === 'paid' AND paid_at !== null
        // But we should proceed if status === 'paid' even if paid_at is null
        $isPaidStatus = $payment->status === 'paid';
        if (!$isPaidStatus && !$force) {
            $this->error("Payment is not paid. Current status: {$payment->status}");
            $this->line("Options:");
            $this->line("  --mark-paid : Mark payment as paid (if you already paid)");
            $this->line("  --force : Force provisioning even if payment is pending");
            $this->line("  --sync-xendit : Sync payment status from Xendit first");
            return 1;
        }

        if (!$isPaidStatus && $force) {
            $this->warn("⚠ Force mode: Proceeding even though payment status is '{$payment->status}'");
            if (!$this->confirm('Continue with force mode?', false)) {
                return 0;
            }
        }

        // Trigger provisioning
        $this->info("Triggering provisioning...");
        $this->newLine();

        try {
            $provisioningService = app(SubscriptionProvisioningService::class);
            $result = $provisioningService->provisionFromPaidLandingSubscription($landingSubscription, $payment);

            if ($result['success']) {
                $this->info("✅ Provisioning successful!");
                $this->newLine();
                $this->line("Tenant ID: " . ($result['tenant']->id ?? 'N/A'));
                $this->line("User ID: " . ($result['user']->id ?? 'N/A'));
                $this->line("Store ID: " . ($result['store']->id ?? 'N/A'));
                $this->line("Subscription ID: " . ($result['subscription']->id ?? 'N/A'));
                
                if (isset($result['temporary_password'])) {
                    $this->newLine();
                    $this->warn("⚠ Temporary Password: {$result['temporary_password']}");
                    $this->warn("   (Save this password - it won't be shown again)");
                }

                // Refresh and show plan
                $landingSubscription->refresh();
                if ($landingSubscription->subscription_id) {
                    $subscription = \App\Models\Subscription::find($landingSubscription->subscription_id);
                    if ($subscription && $subscription->plan) {
                        $this->newLine();
                        $this->info("=== SUBSCRIPTION CREATED ===");
                        $this->line("Plan: {$subscription->plan->name} ({$subscription->plan->slug})");
                        $this->line("Status: {$subscription->status}");
                        $this->line("Billing: {$subscription->billing_cycle}");
                        $this->line("Ends at: {$subscription->ends_at->format('Y-m-d H:i:s')}");
                    }
                }

                return 0;
            } else {
                $this->error("❌ Provisioning failed!");
                $this->error("Error: " . ($result['error'] ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception during provisioning:");
            $this->error($e->getMessage());
            Log::error('Provisioning command failed', [
                'landing_subscription_id' => $landingSubscription->id,
                'payment_id' => $payment->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    /**
     * Sync payment status from Xendit
     */
    private function syncPaymentFromXendit(SubscriptionPayment $payment): void
    {
        try {
            $xenditService = app(XenditService::class);
            $result = $xenditService->getInvoice($payment->xendit_invoice_id);

            $this->line("  Xendit API Response:");
            $this->line("    Success: " . ($result['success'] ?? 'N/A'));
            $this->line("    Status: " . ($result['data']['status'] ?? 'N/A'));

            if ($result && $result['success'] && isset($result['data']['status'])) {
                $invoiceData = $result['data'];
                $payment->updateFromXenditCallback($invoiceData);
                
                // Also update landing subscription
                if ($payment->landingSubscription) {
                    $ls = $payment->landingSubscription;
                    $ls->update([
                        'payment_status' => $payment->status,
                    ]);
                    
                    if ($payment->isPaid()) {
                        $ls->update([
                            'status' => 'paid',
                            'stage' => 'payment_completed',
                            'paid_at' => $payment->paid_at,
                        ]);
                    }
                }

                $this->line("✓ Payment status synced from Xendit: {$payment->status}");
            } else {
                $this->warn("⚠ Could not retrieve invoice from Xendit");
                $this->line("   This might be because:");
                $this->line("   - You're in development mode with dummy invoices");
                $this->line("   - Xendit API key is not configured");
                $this->line("   - Invoice ID doesn't exist in Xendit");
            }
        } catch (\Exception $e) {
            $this->warn("⚠ Error syncing from Xendit: " . $e->getMessage());
        }
    }

    /**
     * Create payment record from Xendit invoice
     */
    private function createPaymentFromXendit(LandingSubscription $landingSubscription): ?SubscriptionPayment
    {
        try {
            $xenditService = app(XenditService::class);
            $result = $xenditService->getInvoice($landingSubscription->xendit_invoice_id);

            if (!$result || !$result['success']) {
                $this->error("Could not retrieve invoice from Xendit");
                $this->line("Creating payment record with pending status...");
                
                // Create with pending status - user can manually mark as paid later
                $payment = SubscriptionPayment::create([
                    'landing_subscription_id' => $landingSubscription->id,
                    'xendit_invoice_id' => $landingSubscription->xendit_invoice_id,
                    'external_id' => 'LS-' . $landingSubscription->id . '-' . time(),
                    'amount' => $landingSubscription->payment_amount,
                    'status' => 'pending',
                    'gateway_response' => ['status' => 'PENDING', 'note' => 'Created manually - sync from Xendit needed'],
                ]);
                
                $this->info("✓ Payment record created (status: pending)");
                $this->warn("⚠ If you already paid, use --mark-paid to provision anyway");
                return $payment;
            }

            $invoiceData = $result['data'];
            $payment = SubscriptionPayment::create([
                'landing_subscription_id' => $landingSubscription->id,
                'xendit_invoice_id' => $landingSubscription->xendit_invoice_id,
                'external_id' => $invoiceData['external_id'] ?? 'LS-' . $landingSubscription->id,
                'amount' => $invoiceData['amount'] ?? $landingSubscription->payment_amount,
                'status' => $this->mapXenditStatus($invoiceData['status'] ?? 'PENDING'),
                'gateway_response' => $invoiceData,
                'paid_at' => isset($invoiceData['paid_at']) ? $invoiceData['paid_at'] : null,
                'expires_at' => isset($invoiceData['expiry_date']) ? $invoiceData['expiry_date'] : null,
            ]);

            $this->info("✓ Payment record created (status: {$payment->status})");
            return $payment;
        } catch (\Exception $e) {
            $this->error("Error creating payment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Map Xendit status to our status
     */
    private function mapXenditStatus(string $xenditStatus): string
    {
        return match(strtoupper($xenditStatus)) {
            'PAID' => 'paid',
            'PENDING' => 'pending',
            'EXPIRED' => 'expired',
            'FAILED' => 'failed',
            default => 'pending'
        };
    }
}
