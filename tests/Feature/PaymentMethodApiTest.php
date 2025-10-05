<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_payment_methods(): void
    {
        Sanctum::actingAs($this->user);

        // Create some payment methods for the user
        $defaultMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => true,
            'gateway' => 'midtrans',
            'type' => 'card',
        ]);

        $otherMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
            'gateway' => 'midtrans',
            'type' => 'va',
        ]);

        $response = $this->getJson('/api/v1/payment-methods');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment methods retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_methods' => [
                        '*' => [
                            'id',
                            'gateway',
                            'type',
                            'display_name',
                            'masked_number',
                            'is_default',
                            'is_usable',
                            'expires_at',
                            'created_at',
                        ],
                    ],
                ],
                'message',
                'meta',
            ]);

        // Check that default method comes first
        $paymentMethods = $response->json('data.payment_methods');
        $this->assertEquals($defaultMethod->id, $paymentMethods[0]['id']);
        $this->assertTrue($paymentMethods[0]['is_default']);
    }

    public function test_can_create_payment_token(): void
    {
        Sanctum::actingAs($this->user);

        // Mock PaymentService to avoid Midtrans configuration issues
        $this->mock(\App\Services\PaymentService::class, function ($mock) {
            $mock->shouldReceive('createPaymentToken')
                ->once()
                ->andReturn([
                    'snap_token' => 'mock_snap_token_123',
                    'redirect_url' => 'https://mock.midtrans.com/redirect',
                ]);
        });

        $response = $this->postJson('/api/v1/payment-methods/create-token', [
            'enabled_payments' => ['credit_card', 'gopay', 'qris'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment token created successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'snap_token',
                    'redirect_url',
                ],
                'message',
                'meta',
            ]);
    }

    public function test_can_save_payment_method(): void
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_type' => 'credit_card',
            'saved_token_id' => 'token_123456',
            'masked_card' => '411111-1111',
            'card_type' => 'credit',
        ];

        $response = $this->postJson('/api/v1/payment-methods', [
            'payment_data' => $paymentData,
            'set_as_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method saved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'payment_method' => [
                        'id',
                        'gateway',
                        'type',
                        'display_name',
                        'masked_number',
                        'is_default',
                        'is_usable',
                        'expires_at',
                    ],
                ],
                'message',
                'meta',
            ]);

        // Verify payment method was created in database
        $this->assertDatabaseHas('payment_methods', [
            'user_id' => $this->user->id,
            'gateway' => 'midtrans',
            'gateway_id' => 'token_123456',
            'is_default' => true,
        ]);
    }

    public function test_can_set_payment_method_as_default(): void
    {
        Sanctum::actingAs($this->user);

        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->postJson("/api/v1/payment-methods/{$paymentMethod->id}/set-default");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method set as default successfully',
                'data' => [
                    'payment_method' => [
                        'id' => $paymentMethod->id,
                        'is_default' => true,
                    ],
                ],
            ]);

        // Verify in database
        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
            'is_default' => true,
        ]);
    }

    public function test_cannot_set_other_users_payment_method_as_default(): void
    {
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/payment-methods/{$paymentMethod->id}/set-default");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_METHOD_NOT_FOUND',
                    'message' => 'Payment method not found or does not belong to you',
                ],
            ]);
    }

    public function test_can_delete_payment_method(): void
    {
        Sanctum::actingAs($this->user);

        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/payment-methods/{$paymentMethod->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method deleted successfully',
            ]);

        // Verify payment method was deleted
        $this->assertDatabaseMissing('payment_methods', [
            'id' => $paymentMethod->id,
        ]);
    }

    public function test_cannot_delete_other_users_payment_method(): void
    {
        $otherUser = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/payment-methods/{$paymentMethod->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_METHOD_NOT_FOUND',
                    'message' => 'Payment method not found or does not belong to you',
                ],
            ]);

        // Verify payment method still exists
        $this->assertDatabaseHas('payment_methods', [
            'id' => $paymentMethod->id,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/payment-methods');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                ],
            ]);
    }

    public function test_validates_create_token_request(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/payment-methods/create-token', [
            'enabled_payments' => ['invalid_payment_type'],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'enabled_payments.0'
                ]
            ]);
    }

    public function test_validates_save_payment_method_request(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/payment-methods', [
            // Missing required payment_data
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'payment_data'
                ]
            ]);
    }
}
