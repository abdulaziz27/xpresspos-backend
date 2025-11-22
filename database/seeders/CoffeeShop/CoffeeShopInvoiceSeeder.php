<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\Invoice;

class CoffeeShopInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic invoices for subscription billing for coffee shop demo.
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
            $this->command->warn('No active subscription found. Skipping invoices.');
            return;
        }

        // Check if invoices already exist
        $existingInvoices = Invoice::where('subscription_id', $subscription->id)->count();

        if ($existingInvoices > 0) {
            $this->command->info("⏭️  Subscription already has {$existingInvoices} invoice(s). Skipping...");
            return;
        }

        $this->command->info("📄 Creating invoices for tenant: {$tenant->name}");

        // Create current month invoice (paid)
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

        $this->command->line("   ✓ Created invoice: {$invoice->invoice_number} - Status: {$invoice->status} - Amount: Rp " . number_format($invoice->total_amount, 0, ',', '.'));
        $this->command->info("✅ Successfully created invoice for tenant: {$tenant->name}");
    }
}

