<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_method_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $paymentMethod->user);
        $this->assertEquals($user->id, $paymentMethod->user->id);
    }

    public function test_payment_method_can_check_if_expired(): void
    {
        $expiredPaymentMethod = PaymentMethod::factory()->create([
            'expires_at' => now()->subDay()
        ]);

        $validPaymentMethod = PaymentMethod::factory()->create([
            'expires_at' => now()->addYear()
        ]);

        $noExpiryPaymentMethod = PaymentMethod::factory()->create([
            'expires_at' => null
        ]);

        $this->assertTrue($expiredPaymentMethod->isExpired());
        $this->assertFalse($validPaymentMethod->isExpired());
        $this->assertFalse($noExpiryPaymentMethod->isExpired());
    }

    public function test_payment_method_can_check_if_usable(): void
    {
        $expiredPaymentMethod = PaymentMethod::factory()->create([
            'expires_at' => now()->subDay()
        ]);

        $validPaymentMethod = PaymentMethod::factory()->create([
            'expires_at' => now()->addYear()
        ]);

        $this->assertFalse($expiredPaymentMethod->isUsable());
        $this->assertTrue($validPaymentMethod->isUsable());
    }

    public function test_payment_method_display_name(): void
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'type' => 'card',
            'last_four' => '1234'
        ]);

        $this->assertEquals('Card ending in 1234', $paymentMethod->display_name);
    }

    public function test_payment_method_masked_number(): void
    {
        $paymentMethod = PaymentMethod::factory()->create([
            'last_four' => '1234'
        ]);

        $this->assertEquals('**** **** **** 1234', $paymentMethod->masked_number);
    }

    public function test_payment_method_can_be_set_as_default(): void
    {
        $user = User::factory()->create();
        
        $paymentMethod1 = PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'is_default' => true
        ]);
        
        $paymentMethod2 = PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'is_default' => false
        ]);

        $paymentMethod2->setAsDefault();

        $this->assertFalse($paymentMethod1->fresh()->is_default);
        $this->assertTrue($paymentMethod2->fresh()->is_default);
    }

    public function test_payment_method_scopes(): void
    {
        $user = User::factory()->create();
        
        $defaultPaymentMethod = PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'is_default' => true,
            'gateway' => 'stripe',
            'type' => 'card',
            'expires_at' => now()->addYear()
        ]);
        
        $expiredPaymentMethod = PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'is_default' => false,
            'gateway' => 'paypal',
            'type' => 'digital_wallet',
            'expires_at' => now()->subDay()
        ]);

        // Test default scope
        $defaultMethods = PaymentMethod::default()->get();
        $this->assertCount(1, $defaultMethods);
        $this->assertEquals($defaultPaymentMethod->id, $defaultMethods->first()->id);

        // Test usable scope
        $usableMethods = PaymentMethod::usable()->get();
        $this->assertCount(1, $usableMethods);
        $this->assertEquals($defaultPaymentMethod->id, $usableMethods->first()->id);

        // Test gateway scope
        $stripeMethods = PaymentMethod::byGateway('stripe')->get();
        $this->assertCount(1, $stripeMethods);
        $this->assertEquals($defaultPaymentMethod->id, $stripeMethods->first()->id);

        // Test type scope
        $cardMethods = PaymentMethod::byType('card')->get();
        $this->assertCount(1, $cardMethods);
        $this->assertEquals($defaultPaymentMethod->id, $cardMethods->first()->id);
    }
}