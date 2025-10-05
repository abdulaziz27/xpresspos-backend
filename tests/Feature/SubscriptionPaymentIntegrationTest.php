<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubscriptionPaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and store
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create();
        $this->user->update(['store_id' => $this->store->id]);

        // Create test plan
        $this->plan = Plan::factory()->create([
            'name' => 'Basic Plan',
            'price' => 99000,
            'annual_price' => 990000,
            'is_active' => true,
        ]);

        // Mock Midtrans configuration
        config([
            'services.midtrans.server_key' => 'test_server_key',
            'services.midtrans.client_key' => 'test_client_key',
            'services.midtrans.is_production' => false,
        ]);
    }

    public function test_can_get_available_plans(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscription-payments/plans');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'plans' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'description',
                            'price',
                            'annual_price',
                            'features',
                            'limits',
                            'is_popular',
                        ]
                    ]
                ],
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
    }

    public function test_can_get_payment_methods(): void
    {
        // Create payment method for user
        PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'gateway' => 'midtrans',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscription-payments/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_methods' => [
                        '*' => [
                            'id',
                            'type',
                            'last_four',
                            'expires_at',
                            'is_default',
                            'metadata',
                        ]
                    ]
                ],
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('data.payment_methods'));
    }

    public function test_can_get_subscription_invoices(): void
    {
        // Create subscription and invoices
        $subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);

        Invoice::factory()->count(3)->create([
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscription-payments/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'invoices' => [
                        '*' => [
                            'id',
                            'invoice_number',
                            'amount',
                            'tax_amount',
                            'total_amount',
                            'status',
                            'due_date',
                            'paid_at',
                            'created_at',
                            'payments',
                        ]
                    ]
                ],
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data.invoices'));
    }

    public function test_can_get_payment_status(): void
    {
        // Create subscription, invoice, and payment
        $subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
        ]);

        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
        ]);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'gateway' => 'midtrans',
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/subscription-payments/invoices/{$invoice->id}/status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'invoice',
                    'payments',
                ],
                'message',
                'meta'
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(1, $response->json('data.payments'));
    }

    public function test_cannot_access_other_store_invoice(): void
    {
        // Create another store and invoice
        $otherStore = Store::factory()->create();
        $otherSubscription = Subscription::factory()->create([
            'store_id' => $otherStore->id,
            'plan_id' => $this->plan->id,
        ]);

        $otherInvoice = Invoice::factory()->create([
            'subscription_id' => $otherSubscription->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/subscription-payments/invoices/{$otherInvoice->id}/status");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVOICE_ACCESS_DENIED',
                ]
            ]);
    }

    public function test_invoice_service_creates_invoice_correctly(): void
    {
        $subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->createInitialInvoice($subscription);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($subscription->id, $invoice->subscription_id);
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals($subscription->amount, $invoice->amount);
        $this->assertNotNull($invoice->invoice_number);
    }

    public function test_subscription_creation_flow(): void
    {
        // Test the subscription creation without payment processing
        $subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);

        // Test invoice creation
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->createInitialInvoice($subscription);

        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        // Test payment creation (without Midtrans API call)
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'gateway' => 'midtrans',
            'status' => 'pending',
            'store_id' => $this->store->id,
        ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'gateway' => 'midtrans',
            'status' => 'pending',
        ]);
    }

    public function test_payment_method_management(): void
    {
        // Test payment method creation
        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'gateway' => 'midtrans',
            'type' => 'card',
            'last_four' => '1234',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('payment_methods', [
            'user_id' => $this->user->id,
            'gateway' => 'midtrans',
            'type' => 'card',
            'last_four' => '1234',
            'is_default' => true,
        ]);

        // Test setting as default
        $paymentMethod->setAsDefault();
        $this->assertTrue($paymentMethod->is_default);
    }

    public function test_invoice_payment_flow(): void
    {
        // Create subscription and invoice
        $subscription = Subscription::factory()->create([
            'store_id' => $this->store->id,
            'plan_id' => $this->plan->id,
        ]);

        $invoice = Invoice::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        // Test payment creation
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'gateway' => 'midtrans',
            'status' => 'pending',
            'store_id' => $this->store->id,
        ]);

        // Test payment completion
        $payment->markAsProcessed();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->processed_at);

        // Test invoice marking as paid
        $invoiceService = app(InvoiceService::class);
        $invoiceService->markInvoiceAsPaid($invoice);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }
}
