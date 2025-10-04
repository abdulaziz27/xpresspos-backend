<?php

namespace Tests\Feature;

use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Midtrans configuration
        config([
            'services.midtrans.server_key' => 'test_server_key',
            'services.midtrans.client_key' => 'test_client_key',
            'services.midtrans.is_production' => false,
        ]);
    }

    public function test_handles_successful_payment_notification(): void
    {
        // Mock PaymentService to return success
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleNotification')
                 ->once()
                 ->andReturn(true);
        });

        $notification = [
            'order_id' => 'INV-123-1234567890',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_id' => 'test_transaction_123',
            'signature_key' => 'test_signature',
        ];

        $response = $this->postJson('/api/v1/webhooks/midtrans', $notification);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Notification processed successfully'
                ]);
    }

    public function test_handles_failed_payment_notification(): void
    {
        // Mock PaymentService to return success (even for failed payments, the processing is successful)
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleNotification')
                 ->once()
                 ->andReturn(true);
        });

        $notification = [
            'order_id' => 'INV-123-1234567890',
            'transaction_status' => 'deny',
            'status_code' => '202',
            'gross_amount' => '100000.00',
            'transaction_id' => 'test_transaction_456',
            'signature_key' => 'test_signature',
        ];

        $response = $this->postJson('/api/v1/webhooks/midtrans', $notification);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => 'Notification processed successfully'
                ]);
    }

    public function test_rejects_invalid_notification_format(): void
    {
        $notification = [
            'invalid_field' => 'test',
        ];

        $response = $this->postJson('/api/v1/webhooks/midtrans', $notification);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid notification format'
                ]);
    }

    public function test_handles_notification_processing_exception(): void
    {
        // Mock PaymentService to throw exception
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleNotification')
                 ->andThrow(new \Exception('Processing failed'));
        });

        $notification = [
            'order_id' => 'INV-123-1234567890',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_id' => 'test_transaction_789',
            'signature_key' => 'test_signature',
        ];

        $response = $this->postJson('/api/v1/webhooks/midtrans', $notification);

        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Internal server error'
                ]);
    }

    public function test_handles_payment_service_returning_false(): void
    {
        // Mock PaymentService to return false (processing failed)
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleNotification')
                 ->once()
                 ->andReturn(false);
        });

        $notification = [
            'order_id' => 'INV-123-1234567890',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_id' => 'test_transaction_999',
            'signature_key' => 'test_signature',
        ];

        $response = $this->postJson('/api/v1/webhooks/midtrans', $notification);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Failed to process notification'
                ]);
    }

    public function test_webhook_endpoint_is_accessible_without_authentication(): void
    {
        // Mock PaymentService
        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('handleNotification')
                 ->andReturn(true);
        });

        $notification = [
            'order_id' => 'INV-123-1234567890',
            'transaction_status' => 'settlement',
        ];

        // Should not require authentication
        $response = $this->postJson('/api/v1/webhooks/midtrans', $notification);
        
        // Should not return 401 Unauthorized
        $this->assertNotEquals(401, $response->getStatusCode());
    }
}