<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_belongs_to_subscription(): void
    {
        $subscription = Subscription::factory()->create();
        $invoice = Invoice::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertInstanceOf(Subscription::class, $invoice->subscription);
        $this->assertEquals($subscription->id, $invoice->subscription->id);
    }

    public function test_invoice_generates_number_on_creation(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertNotEmpty($invoice->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
    }

    public function test_invoice_calculates_total_amount_on_creation(): void
    {
        $invoice = Invoice::factory()->create([
            'amount' => 100.00,
            'tax_amount' => 10.00,
            'total_amount' => null // Should be calculated
        ]);

        $this->assertEquals(110.00, $invoice->total_amount);
    }

    public function test_invoice_status_checks(): void
    {
        $paidInvoice = Invoice::factory()->create([
            'status' => 'paid',
            'paid_at' => now()
        ]);

        $pendingInvoice = Invoice::factory()->create([
            'status' => 'pending',
            'due_date' => now()->addDays(7)
        ]);

        $overdueInvoice = Invoice::factory()->create([
            'status' => 'pending',
            'due_date' => now()->subDays(7)
        ]);

        $failedInvoice = Invoice::factory()->create(['status' => 'failed']);
        $refundedInvoice = Invoice::factory()->create(['status' => 'refunded']);

        $this->assertTrue($paidInvoice->isPaid());
        $this->assertTrue($pendingInvoice->isPending());
        $this->assertTrue($overdueInvoice->isOverdue());
        $this->assertTrue($failedInvoice->isFailed());
        $this->assertTrue($refundedInvoice->isRefunded());

        $this->assertFalse($pendingInvoice->isPaid());
        $this->assertFalse($pendingInvoice->isOverdue());
    }

    public function test_invoice_can_be_marked_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'pending']);

        $invoice->markAsPaid();

        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_invoice_can_be_marked_as_failed(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'pending']);

        $invoice->markAsFailed();

        $this->assertEquals('failed', $invoice->status);
    }

    public function test_invoice_can_be_marked_as_refunded(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'paid']);

        $invoice->markAsRefunded();

        $this->assertEquals('refunded', $invoice->status);
    }

    public function test_invoice_days_until_due_calculation(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->addDays(5)
        ]);

        // Allow for some variance due to timing
        $this->assertGreaterThanOrEqual(4, $invoice->days_until_due);
        $this->assertLessThanOrEqual(5, $invoice->days_until_due);
    }

    public function test_invoice_scopes(): void
    {
        $paidInvoice = Invoice::factory()->create(['status' => 'paid']);
        $pendingInvoice = Invoice::factory()->create([
            'status' => 'pending',
            'due_date' => now()->addDays(7)
        ]);
        $overdueInvoice = Invoice::factory()->create([
            'status' => 'pending',
            'due_date' => now()->subDays(7)
        ]);
        $dueSoonInvoice = Invoice::factory()->create([
            'status' => 'pending',
            'due_date' => now()->addDays(3)
        ]);

        // Test paid scope
        $paidInvoices = Invoice::paid()->get();
        $this->assertCount(1, $paidInvoices);
        $this->assertEquals($paidInvoice->id, $paidInvoices->first()->id);

        // Test pending scope
        $pendingInvoices = Invoice::pending()->get();
        $this->assertCount(3, $pendingInvoices);

        // Test overdue scope
        $overdueInvoices = Invoice::overdue()->get();
        $this->assertCount(1, $overdueInvoices);
        $this->assertEquals($overdueInvoice->id, $overdueInvoices->first()->id);

        // Test due soon scope
        $dueSoonInvoices = Invoice::dueSoon(7)->get();
        $this->assertCount(2, $dueSoonInvoices); // pendingInvoice and dueSoonInvoice
    }

    public function test_invoice_number_generation(): void
    {
        // Create actual invoices to test the sequence
        $invoice1 = Invoice::factory()->create();
        $invoice2 = Invoice::factory()->create();

        $this->assertNotEquals($invoice1->invoice_number, $invoice2->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice1->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice2->invoice_number);
        
        // Test the static method separately
        $number = Invoice::generateInvoiceNumber();
        $this->assertStringStartsWith('INV-', $number);
        $this->assertStringContainsString(now()->format('Ymd'), $number);
    }
}