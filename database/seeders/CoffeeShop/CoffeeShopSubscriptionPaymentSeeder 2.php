<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Invoice;

class CoffeeShopSubscriptionPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic subscription payment history for coffee shop demo.
     */
    public function run(): void
    {
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Get active subscription
        $subscription = Subscription::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            $this->command->warn('No active subscription found. Skipping payment history.');
            return;
        }

        // Check if payments already exist
        $existingPayments = SubscriptionPayment::where('subscription_id', $subscription->id)->count();

        if ($existingPayments > 0) {
            $this->command->info("⏭️  Subscription already has {$existingPayments} payment(s). Skipping...");
            return;
        }

        $this->command->info("💳 Creating subscription payments for tenant: {$tenant->name}");

        // Get or create invoice for this subscription
        $invoice = Invoice::where('subscription_id', $subscription->id)
            ->where('status', 'paid')
            ->first();

        if (!$invoice) {
            // Create invoice first
            $invoice = Invoice::create([
                'subscription_id' => $subscription->id,
                'invoice_number' => null, // Auto-generated
                'amount' => $subscription->amount,
                'tax_amount' => 0,
                'total_amount' => $subscription->amount,
                'status' => 'paid',
                'due_date' => $subscription->starts_at->copy()->addDays(7),
                'paid_at' => $subscription->starts_at->copy()->addDays(2),
                'line_items' => [
                    [
                        'description' => "Subscription {$subscription->plan->name} - {$subscription->billing_cycle}",
                        'amount' => $subscription->amount,
                    ],
                ],
                'metadata' => [
                    'billing_cycle' => $subscription->billing_cycle,
                    'period_start' => $subscription->starts_at->toDateString(),
                    'period_end' => $subscription->ends_at->toDateString(),
                ],
            ]);
        }

        // Create payment record
        $payment = SubscriptionPayment::create([
            'landing_subscription_id' => null,
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
            'xendit_invoice_id' => 'DEMO-' . strtoupper(uniqid()),
            'external_id' => SubscriptionPayment::generateExternalId(),
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'Bank Transfer',
            'amount' => $subscription->amount,
            'gateway_fee' => 0,
            'status' => 'paid',
            'gateway_response' => [
                'status' => 'PAID',
                'paid_at' => $invoice->paid_at->toIso8601String(),
                'payment_method' => 'BANK_TRANSFER',
            ],
            'paid_at' => $invoice->paid_at,
            'expires_at' => $invoice->due_date,
        ]);

        $this->command->line("   ✓ Created payment: {$payment->external_id} - Amount: Rp " . number_format($payment->amount, 0, ',', '.'));
        $this->command->info("✅ Successfully created subscription payment for tenant: {$tenant->name}");
    }
}

