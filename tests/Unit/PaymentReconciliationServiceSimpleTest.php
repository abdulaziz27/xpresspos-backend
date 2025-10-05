<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Services\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReconciliationServiceSimpleTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentReconciliationService $reconciliationService;
    protected Store $store;
    protected Plan $plan;
    protected Subscription $subscription;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reconciliationService = app(PaymentReconciliationService::class);

        $this->store = Store::factory()->create();
        $this->plan = Plan::factory()->create([
            'name' => 'Test Plan',
            'price' => 100000,
        ]);

        $this->subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        $this->invoice = Invoice::factory()->create([
            'subscription_id' => $this->subscription->id,
            'status' => 'pending',
        ]);
    }

    public function test_can_get_reconciliation_summary(): void
    {
        $summary = $this->reconciliationService->getReconciliationSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('pending_payments', $summary);
        $this->assertArrayHasKey('failed_payments_7_days', $summary);
        $this->assertArrayHasKey('overdue_invoices', $summary);
        $this->assertArrayHasKey('last_reconciliation', $summary);

        // Should be 0 since no payments exist yet
        $this->assertEquals(0, $summary['pending_payments']);
        $this->assertEquals(0, $summary['failed_payments_7_days']);
    }

    public function test_should_create_retry_invoice_for_failed_payment(): void
    {
        $failedPayment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'store_id' => $this->store->id,
        ]);

        // Reload the payment with relationships
        $failedPayment = $failedPayment->load('invoice.subscription');

        // Debug: Check if relationships are loaded
        $this->assertNotNull($failedPayment->invoice);
        $this->assertNotNull($failedPayment->invoice->subscription);
        $this->assertEquals('pending', $failedPayment->invoice->status);
        $this->assertEquals('active', $failedPayment->invoice->subscription->status);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->reconciliationService);
        $method = $reflection->getMethod('shouldCreateRetryInvoice');
        $method->setAccessible(true);

        $shouldCreate = $method->invoke($this->reconciliationService, $failedPayment);

        $this->assertTrue($shouldCreate);
    }

    public function test_should_not_create_retry_invoice_for_cancelled_subscription(): void
    {
        $this->subscription->update(['status' => 'cancelled']);

        $failedPayment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'store_id' => $this->store->id,
        ]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->reconciliationService);
        $method = $reflection->getMethod('shouldCreateRetryInvoice');
        $method->setAccessible(true);

        $shouldCreate = $method->invoke($this->reconciliationService, $failedPayment);

        $this->assertFalse($shouldCreate);
    }

    public function test_should_not_create_retry_invoice_for_already_paid_invoice(): void
    {
        $this->invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $failedPayment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'store_id' => $this->store->id,
        ]);

        // Reload the payment with relationships
        $failedPayment = $failedPayment->load('invoice.subscription');

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->reconciliationService);
        $method = $reflection->getMethod('shouldCreateRetryInvoice');
        $method->setAccessible(true);

        $shouldCreate = $method->invoke($this->reconciliationService, $failedPayment);

        $this->assertFalse($shouldCreate);
    }

    public function test_should_not_create_retry_invoice_after_max_retries(): void
    {
        $failedPayment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'store_id' => $this->store->id,
        ]);

        // Create 3 retry invoices (max limit)
        for ($i = 0; $i < 3; $i++) {
            Invoice::factory()->create([
                'subscription_id' => $this->subscription->id,
                'metadata' => ['type' => 'retry'],
                'created_at' => now()->addMinutes($i),
            ]);
        }

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->reconciliationService);
        $method = $reflection->getMethod('shouldCreateRetryInvoice');
        $method->setAccessible(true);

        $shouldCreate = $method->invoke($this->reconciliationService, $failedPayment);

        $this->assertFalse($shouldCreate);
    }

    public function test_can_create_retry_invoice(): void
    {
        $failedPayment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'store_id' => $this->store->id,
        ]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->reconciliationService);
        $method = $reflection->getMethod('createRetryInvoice');
        $method->setAccessible(true);

        $method->invoke($this->reconciliationService, $failedPayment);

        // Verify retry invoice was created
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $this->subscription->id,
            'metadata->type' => 'retry',
            'metadata->original_invoice_id' => $this->invoice->id,
            'metadata->failed_payment_id' => $failedPayment->id,
        ]);
    }

    public function test_cleanup_old_payments_returns_zero_when_none_exist(): void
    {
        $deletedCount = $this->reconciliationService->cleanupOldPayments(90);

        $this->assertEquals(0, $deletedCount);
    }

    public function test_process_failed_payments_returns_empty_results_when_none_exist(): void
    {
        $results = $this->reconciliationService->processFailedPayments();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('processed', $results);
        $this->assertArrayHasKey('retry_invoices_created', $results);
        $this->assertArrayHasKey('errors', $results);

        $this->assertEquals(0, $results['processed']);
        $this->assertEquals(0, $results['retry_invoices_created']);
        $this->assertEmpty($results['errors']);
    }

    public function test_reconcile_all_pending_payments_returns_empty_results_when_none_exist(): void
    {
        $results = $this->reconciliationService->reconcileAllPendingPayments();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('processed', $results);
        $this->assertArrayHasKey('updated', $results);
        $this->assertArrayHasKey('errors', $results);

        $this->assertEquals(0, $results['processed']);
        $this->assertEquals(0, $results['updated']);
        $this->assertEmpty($results['errors']);
    }
}
