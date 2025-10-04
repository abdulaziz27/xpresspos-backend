<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    public function test_payment_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(PaymentService::class, $this->paymentService);
    }

    public function test_maps_midtrans_status_correctly(): void
    {
        $reflection = new \ReflectionClass($this->paymentService);
        $method = $reflection->getMethod('mapMidtransStatus');
        $method->setAccessible(true);

        $this->assertEquals('completed', $method->invoke($this->paymentService, 'capture'));
        $this->assertEquals('completed', $method->invoke($this->paymentService, 'settlement'));
        $this->assertEquals('pending', $method->invoke($this->paymentService, 'pending'));
        $this->assertEquals('failed', $method->invoke($this->paymentService, 'deny'));
        $this->assertEquals('failed', $method->invoke($this->paymentService, 'cancel'));
        $this->assertEquals('failed', $method->invoke($this->paymentService, 'expire'));
        $this->assertEquals('refunded', $method->invoke($this->paymentService, 'refund'));
        $this->assertEquals('failed', $method->invoke($this->paymentService, 'settlement', 'deny'));
    }

    public function test_user_can_have_midtrans_customer_id(): void
    {
        $user = User::factory()->create([
            'midtrans_customer_id' => 'customer_123_test'
        ]);

        $this->assertEquals('customer_123_test', $user->midtrans_customer_id);
    }

    public function test_payment_method_can_be_created_with_gateway_info(): void
    {
        $user = User::factory()->create();
        
        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'gateway' => 'midtrans',
            'gateway_id' => 'token_test123',
            'type' => 'card',
            'last_four' => '4242',
        ]);

        $this->assertEquals('midtrans', $paymentMethod->gateway);
        $this->assertEquals('token_test123', $paymentMethod->gateway_id);
        $this->assertEquals('card', $paymentMethod->type);
        $this->assertEquals('4242', $paymentMethod->last_four);
    }

    public function test_invoice_can_be_created_with_subscription(): void
    {
        $invoice = Invoice::factory()->create([
            'total_amount' => 100.00
        ]);

        $this->assertEquals(100.00, $invoice->total_amount);
        $this->assertNotNull($invoice->subscription);
        $this->assertNotNull($invoice->subscription->store);
    }

    public function test_payment_service_has_required_methods(): void
    {
        $this->assertTrue(method_exists($this->paymentService, 'getOrCreateMidtransCustomer'));
        $this->assertTrue(method_exists($this->paymentService, 'createPaymentToken'));
        $this->assertTrue(method_exists($this->paymentService, 'savePaymentMethod'));
        $this->assertTrue(method_exists($this->paymentService, 'createPaymentTransaction'));
        $this->assertTrue(method_exists($this->paymentService, 'processPayment'));
        $this->assertTrue(method_exists($this->paymentService, 'handleNotification'));
        $this->assertTrue(method_exists($this->paymentService, 'deletePaymentMethod'));
    }
}