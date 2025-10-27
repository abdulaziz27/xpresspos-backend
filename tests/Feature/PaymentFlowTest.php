<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Services\XenditService;
use App\Models\LandingSubscription;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that XenditService works in development mode without real API keys.
     */
    public function test_xendit_service_development_mode(): void
    {
        // Set dummy environment
        config(['xendit.api_key' => 'dummy_key_for_development']);
        config(['xendit.webhook_token' => 'dummy_token_for_development']);

        $xenditService = app(XenditService::class);

        // Test creating dummy invoice
        $invoiceData = [
            'amount' => 599000,
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'description' => 'Test subscription'
        ];

        $result = $xenditService->createInvoice($invoiceData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertStringStartsWith('dummy_', $result['data']['id']);
        $this->assertEquals(599000, $result['data']['amount']);
    }

    /**
     * Test pricing page loads correctly.
     */
    public function test_pricing_page_loads(): void
    {
        $response = $this->get('/pricing');

        $response->assertStatus(200);
        $response->assertSee('Pilih Paket Terbaik');
        $response->assertSee('Basic');
        $response->assertSee('Professional');
        $response->assertSee('Enterprise');
    }

    /**
     * Test checkout page with valid plan parameters.
     */
    public function test_checkout_page_with_valid_plan(): void
    {
        $response = $this->get('/checkout?plan=pro&billing=monthly');

        $response->assertStatus(200);
        $response->assertSee('Checkout Subscription');
        $response->assertSee('XpressPOS Professional');
    }

    /**
     * Test checkout page validation with invalid plan.
     */
    public function test_checkout_page_validation(): void
    {
        $response = $this->get('/checkout?plan=invalid&billing=monthly');

        $response->assertStatus(302); // Redirect due to validation error
    }

    /**
     * Test subscription creation flow.
     */
    public function test_subscription_creation_flow(): void
    {
        $subscriptionData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '08123456789',
            'business_name' => 'Test Business',
            'business_type' => 'restaurant',
            'plan_id' => 'pro',
            'billing_cycle' => 'monthly',
            'payment_method' => 'bank_transfer'
        ];

        $response = $this->post('/subscription', $subscriptionData);

        // Should redirect to payment page
        $response->assertStatus(302);
        
        // Check if landing subscription was created
        $this->assertDatabaseHas('landing_subscriptions', [
            'email' => 'john@example.com',
            'business_name' => 'Test Business',
            'plan_id' => 'pro',
            'billing_cycle' => 'monthly'
        ]);
    }

    /**
     * Test customer dashboard access.
     */
    public function test_customer_dashboard_access(): void
    {
        // Create a test subscription
        $subscription = LandingSubscription::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '08123456789',
            'business_name' => 'Test Business',
            'business_type' => 'restaurant',
            'plan_id' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'paid',
            'payment_amount' => 599000
        ]);

        $response = $this->get('/customer-dashboard?email=test@example.com');

        $response->assertStatus(200);
        $response->assertSee('Dashboard Customer');
        $response->assertSee('Test Business');
        $response->assertSee('test@example.com');
    }

    /**
     * Test webhook validation in development mode.
     */
    public function test_webhook_validation_development_mode(): void
    {
        config(['xendit.webhook_token' => 'dummy_token_for_development']);
        
        $xenditService = app(XenditService::class);
        
        // In development mode, validation should always pass
        $result = $xenditService->validateWebhook('test payload', 'any signature');
        
        $this->assertTrue($result);
    }

    /**
     * Test API endpoint for creating subscription payment.
     */
    public function test_api_create_subscription_payment(): void
    {
        // Create a landing subscription first
        $subscription = LandingSubscription::create([
            'name' => 'API Test Customer',
            'email' => 'api@example.com',
            'phone' => '08123456789',
            'business_name' => 'API Test Business',
            'business_type' => 'retail',
            'plan_id' => 'basic',
            'billing_cycle' => 'monthly',
            'status' => 'pending_payment'
        ]);

        $paymentData = [
            'landing_subscription_id' => $subscription->id,
            'amount' => 99000,
            'payment_method' => 'bank_transfer',
            'description' => 'Test payment'
        ];

        $response = $this->postJson('/api/v1/subscription-payments/create', $paymentData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'subscription_payment_id',
                'xendit_invoice_id',
                'payment_url',
                'amount',
                'status'
            ]
        ]);
    }
}