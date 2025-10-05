<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoiceService;
    protected Store $store;
    protected Plan $plan;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceService = app(InvoiceService::class);

        $this->store = Store::factory()->create();
        $this->plan = Plan::factory()->create([
            'name' => 'Test Plan',
            'price' => 100000,
            'annual_price' => 1000000,
        ]);

        $this->subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
            'amount' => 100000,
            'billing_cycle' => 'monthly',
        ]);
    }

    public function test_can_create_initial_invoice(): void
    {
        $invoice = $this->invoiceService->createInitialInvoice($this->subscription);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($this->subscription->id, $invoice->subscription_id);
        $this->assertEquals(100000, $invoice->amount);
        $this->assertEquals(11000, $invoice->tax_amount); // 11% VAT
        $this->assertEquals(111000, $invoice->total_amount);
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals('initial', $invoice->metadata['type']);
        $this->assertNotNull($invoice->invoice_number);
    }

    public function test_can_create_renewal_invoice(): void
    {
        $invoice = $this->invoiceService->createRenewalInvoice($this->subscription);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($this->subscription->id, $invoice->subscription_id);
        $this->assertEquals(100000, $invoice->amount);
        $this->assertEquals(11000, $invoice->tax_amount);
        $this->assertEquals(111000, $invoice->total_amount);
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals('renewal', $invoice->metadata['type']);
        $this->assertNotNull($invoice->invoice_number);
    }

    public function test_can_create_upgrade_invoice(): void
    {
        $proratedAmount = 50000;
        $upgradeDetails = [
            'upgraded_from' => 'basic',
            'upgraded_to' => 'pro',
        ];

        $invoice = $this->invoiceService->createUpgradeInvoice(
            $this->subscription,
            $proratedAmount,
            $upgradeDetails
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($this->subscription->id, $invoice->subscription_id);
        $this->assertEquals(50000, $invoice->amount);
        $this->assertEquals(5500, $invoice->tax_amount); // 11% VAT
        $this->assertEquals(55500, $invoice->total_amount);
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals('upgrade', $invoice->metadata['type']);
        $this->assertEquals('basic', $invoice->metadata['upgraded_from']);
        $this->assertEquals('pro', $invoice->metadata['upgraded_to']);
    }

    public function test_can_get_pending_invoices(): void
    {
        // Create multiple invoices with different statuses
        Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'pending',
        ]);

        Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'paid',
        ]);

        Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'pending',
        ]);

        $pendingInvoices = $this->invoiceService->getPendingInvoices($this->subscription);

        $this->assertCount(2, $pendingInvoices);
        $this->assertTrue($pendingInvoices->every(fn($invoice) => $invoice->status === 'pending'));
    }

    public function test_can_get_overdue_invoices(): void
    {
        // Create invoices with different due dates
        Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'pending',
            'due_date' => now()->subDays(5), // Overdue
        ]);

        Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'pending',
            'due_date' => now()->addDays(5), // Not overdue
        ]);

        Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'paid',
            'due_date' => now()->subDays(10), // Paid, so not overdue
        ]);

        $overdueInvoices = $this->invoiceService->getOverdueInvoices($this->subscription);

        $this->assertCount(1, $overdueInvoices);
        $this->assertTrue($overdueInvoices->first()->isOverdue());
    }

    public function test_can_mark_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'pending',
            'metadata' => ['type' => 'renewal'],
        ]);

        $this->invoiceService->markInvoiceAsPaid($invoice);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_invoice_number_generation(): void
    {
        $invoice1 = $this->invoiceService->createInitialInvoice($this->subscription);
        $invoice2 = $this->invoiceService->createInitialInvoice($this->subscription);

        $this->assertNotEquals($invoice1->invoice_number, $invoice2->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice1->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice2->invoice_number);
    }

    public function test_tax_calculation(): void
    {
        $invoice = $this->invoiceService->createInitialInvoice($this->subscription);

        // 11% VAT on 100000 = 11000
        $this->assertEquals(11000, $invoice->tax_amount);
        $this->assertEquals(111000, $invoice->total_amount);
    }

    public function test_line_items_generation(): void
    {
        $invoice = $this->invoiceService->createInitialInvoice($this->subscription);

        $this->assertIsArray($invoice->line_items);
        $this->assertCount(1, $invoice->line_items);

        $lineItem = $invoice->line_items[0];
        $this->assertEquals('subscription', $lineItem['id']);
        $this->assertStringContainsString('Test Plan', $lineItem['name']);
        $this->assertEquals(1, $lineItem['quantity']);
        $this->assertEquals(100000, $lineItem['unit_price']);
        $this->assertEquals(100000, $lineItem['total_price']);
    }
}
