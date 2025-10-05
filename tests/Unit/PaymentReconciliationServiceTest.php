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

class PaymentReconciliationServiceTest extends TestCase
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

        // Create a user with store_id for authentication
        $this->user = \App\Models\User::factory()->create([
            'store_id' => $this->store->id,
        ]);

        // Authenticate the user to bypass store scoping
        $this->actingAs($this->user);
    }

    public function test_can_get_reconciliation_summary(): void
    {
        // Create some test payments
        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'pending',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_pending_' . uniqid(),
            'store_id' => $this->store->id,
        ]);

        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_failed_' . uniqid(),
            'store_id' => $this->store->id,
            'created_at' => now()->subDays(3),
        ]);

        $summary = $this->reconciliationService->getReconciliationSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('pending_payments', $summary);
        $this->assertArrayHasKey('failed_payments_7_days', $summary);
        $this->assertArrayHasKey('overdue_invoices', $summary);
        $this->assertArrayHasKey('last_reconciliation', $summary);

        $this->assertEquals(1, $summary['pending_payments']);
        $this->assertEquals(1, $summary['failed_payments_7_days']);
    }

    public function test_can_cleanup_old_payments(): void
    {
        // Create old failed payment
        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_old_' . uniqid(),
            'store_id' => $this->store->id,
            'created_at' => now()->subDays(100),
        ]);

        // Create recent failed payment (should not be deleted)
        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_recent_' . uniqid(),
            'store_id' => $this->store->id,
            'created_at' => now()->subDays(10),
        ]);

        $deletedCount = $this->reconciliationService->cleanupOldPayments(90);

        $this->assertEquals(1, $deletedCount);

        // Verify only the old payment was deleted
        $this->assertDatabaseMissing('payments', [
            'created_at' => now()->subDays(100),
        ]);

        $this->assertDatabaseHas('payments', [
            'created_at' => now()->subDays(10),
        ]);
    }

    public function test_should_create_retry_invoice_for_failed_payment(): void
    {
        $failedPayment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_retry_' . uniqid(),
            'store_id' => $this->store->id,
        ]);

        // Reload the payment with relationships
        $failedPayment = $failedPayment->load('invoice.subscription');

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
            'gateway_transaction_id' => 'test_cancelled_' . uniqid(),
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
            'gateway_transaction_id' => 'test_paid_' . uniqid(),
            'store_id' => $this->store->id,
        ]);

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
            'gateway_transaction_id' => 'test_max_retries_' . uniqid(),
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
            'gateway_transaction_id' => 'test_create_retry_' . uniqid(),
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

    public function test_process_failed_payments_creates_retry_invoices(): void
    {
        // Create failed payment
        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'failed',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_process_failed_' . uniqid(),
            'store_id' => $this->store->id,
            'created_at' => now()->subDays(5),
        ]);

        $results = $this->reconciliationService->processFailedPayments();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('processed', $results);
        $this->assertArrayHasKey('retry_invoices_created', $results);
        $this->assertArrayHasKey('errors', $results);

        $this->assertEquals(1, $results['processed']);
        $this->assertEquals(1, $results['retry_invoices_created']);
        $this->assertEmpty($results['errors']);
    }

    public function test_reconcile_all_pending_payments(): void
    {
        // Create pending payment
        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status' => 'pending',
            'gateway' => 'midtrans',
            'gateway_transaction_id' => 'test_reconcile_pending_' . uniqid(),
            'store_id' => $this->store->id,
            'created_at' => now()->subDays(2),
        ]);

        // Mock Midtrans Transaction::status method
        $this->mock(\Midtrans\Transaction::class, function ($mock) {
            $mock->shouldReceive('status')
                ->andReturn([
                    'transaction_status' => 'settlement',
                    'fraud_status' => 'accept',
                ]);
        });

        $results = $this->reconciliationService->reconcileAllPendingPayments();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('processed', $results);
        $this->assertArrayHasKey('updated', $results);
        $this->assertArrayHasKey('errors', $results);

        $this->assertEquals(1, $results['processed']);
    }
}
