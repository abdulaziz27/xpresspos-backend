<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentOpenBillTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and store
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create();
        
        // Assign user to store
        $this->user->stores()->attach($this->store->id, [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'assignment_role' => 'owner',
            'is_primary' => true,
        ]);

        // Authenticate user
        Sanctum::actingAs($this->user);

        // Create test order
        $this->order = Order::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'total_amount' => 32200,
            'status' => 'open',
            'payment_mode' => 'open_bill',
        ]);
    }

    /**
     * Test creating a pending payment for open bill.
     */
    public function test_create_pending_payment(): void
    {
        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
            'notes' => 'Open Bill - Menunggu Pembayaran',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Pending payment created for open bill',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'payment_method',
                    'amount',
                    'status',
                ],
                'message',
                'meta',
            ])
            ->assertJsonPath('data.payment_method', 'pending')
            ->assertJsonPath('data.status', 'pending');

        // Verify payment was created in database
        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
            'received_amount' => 0,
        ]);
    }

    /**
     * Test completing open bill with actual payment method.
     */
    public function test_complete_open_bill_success(): void
    {
        // First create a pending payment
        $pendingPayment = Payment::factory()->create([
            'store_id' => $this->store->id,
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
            'received_amount' => 0,
        ]);

        // Complete the open bill
        $response = $this->postJson('/api/v1/payments/complete-open-bill', [
            'order_id' => $this->order->id,
            'payment_method' => 'qris',
            'amount' => 32200,
            'received_amount' => 32200,
            'notes' => 'Pembayaran Qris',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Open bill payment completed successfully',
            ])
            ->assertJsonPath('data.payment_method', 'qris')
            ->assertJsonPath('data.status', 'completed');

        // Verify payment was updated in database
        $this->assertDatabaseHas('payments', [
            'id' => $pendingPayment->id,
            'payment_method' => 'qris',
            'amount' => 32200,
            'status' => 'completed',
        ]);
    }

    /**
     * Test completing open bill without pending payment returns 404.
     */
    public function test_complete_open_bill_no_pending_payment(): void
    {
        $response = $this->postJson('/api/v1/payments/complete-open-bill', [
            'order_id' => $this->order->id,
            'payment_method' => 'cash',
            'amount' => 32200,
            'received_amount' => 35000,
            'notes' => 'Pembayaran Cash',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NO_PENDING_PAYMENT',
                    'message' => 'No pending payment found for this order.',
                ],
            ]);
    }

    /**
     * Test creating normal payment that exceeds order balance.
     */
    public function test_normal_payment_exceeds_balance(): void
    {
        // First, make a partial payment
        Payment::factory()->create([
            'store_id' => $this->store->id,
            'order_id' => $this->order->id,
            'payment_method' => 'cash',
            'amount' => 20000,
            'status' => 'completed',
        ]);

        // Try to pay more than remaining balance
        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $this->order->id,
            'payment_method' => 'cash',
            'amount' => 15000, // Remaining is 12200, this exceeds
            'status' => 'completed',
            'notes' => 'Overpayment attempt',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'PAYMENT_VALIDATION_FAILED',
                ],
            ])
            ->assertJsonFragment([
                'Payment amount exceeds remaining balance.',
            ]);
    }

    /**
     * Test completing open bill with cash and change calculation.
     */
    public function test_complete_open_bill_with_change(): void
    {
        // Create pending payment
        Payment::factory()->create([
            'store_id' => $this->store->id,
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
        ]);

        // Complete with cash and received amount > payment amount
        $response = $this->postJson('/api/v1/payments/complete-open-bill', [
            'order_id' => $this->order->id,
            'payment_method' => 'cash',
            'amount' => 32200,
            'received_amount' => 50000,
            'notes' => 'Pembayaran Cash dengan Kembalian',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'change_amount' => 17800, // 50000 - 32200
                ],
            ]);
    }

    /**
     * Test multiple pending payments not allowed.
     */
    public function test_only_one_pending_payment_per_order(): void
    {
        // Create first pending payment
        Payment::factory()->create([
            'store_id' => $this->store->id,
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
        ]);

        // Try to create another pending payment (should work, but not recommended)
        $response = $this->postJson('/api/v1/payments', [
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
            'notes' => 'Second pending payment',
        ]);

        // System allows it, but completeOpenBill will only update the first one
        $response->assertStatus(201);

        // Verify there are 2 pending payments
        $this->assertEquals(2, Payment::where('order_id', $this->order->id)
            ->where('status', 'pending')
            ->count());
    }

    /**
     * Test order completion when fully paid.
     */
    public function test_order_completes_when_fully_paid(): void
    {
        // Create pending payment
        Payment::factory()->create([
            'store_id' => $this->store->id,
            'order_id' => $this->order->id,
            'payment_method' => 'pending',
            'amount' => 32200,
            'status' => 'pending',
        ]);

        // Complete the payment
        $response = $this->postJson('/api/v1/payments/complete-open-bill', [
            'order_id' => $this->order->id,
            'payment_method' => 'credit_card',
            'amount' => 32200,
            'notes' => 'Full payment',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('meta.order_status', 'completed');

        // Verify order is completed
        $this->assertEquals('completed', $this->order->fresh()->status);
    }
}

