<?php

namespace Database\Seeders\CoffeeShop;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Store;

class CoffeeShopPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic payments for completed orders.
     */
    public function run(): void
    {
        $tenant = \App\Models\Tenant::first();
        if (!$tenant) {
            $this->command->error('No tenant found. Make sure StoreSeeder runs first.');
            return;
        }
        
        $tenantId = $tenant->id;

        // Get all completed orders from all stores of this tenant
        $orders = Order::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDoesntHave('payments') // Only orders without payments
            ->get();

        $paymentMethods = ['cash', 'credit_card', 'debit_card', 'qris', 'bank_transfer', 'e_wallet'];
        $paymentMethodWeights = [
            'cash' => 40,        // 40% cash
            'qris' => 25,        // 25% QRIS
            'e_wallet' => 15,    // 15% e-wallet
            'debit_card' => 10,  // 10% debit card
            'credit_card' => 5,  // 5% credit card
            'bank_transfer' => 5, // 5% bank transfer
        ];

        $paymentCount = 0;

        foreach ($orders as $order) {
            // Determine payment method based on weights
            $random = rand(1, 100);
            $cumulative = 0;
            $selectedMethod = 'cash';

            foreach ($paymentMethodWeights as $method => $weight) {
                $cumulative += $weight;
                if ($random <= $cumulative) {
                    $selectedMethod = $method;
                    break;
                }
            }

            // Calculate payment amount
            $amount = $order->total_amount;
            
            // For cash payments, sometimes customer gives more (for change calculation)
            $receivedAmount = $amount;
            if ($selectedMethod === 'cash' && rand(0, 100) < 60) {
                // 60% chance customer gives rounded amount
                $receivedAmount = ceil($amount / 1000) * 1000; // Round up to nearest 1000
            }

            // Payment status (most are completed)
            $status = 'completed';
            if (rand(0, 100) < 3) { // 3% chance pending (rare)
                $status = 'pending';
            }

            // Create payment
            $payment = Payment::query()->withoutGlobalScopes()->create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'payment_method' => $selectedMethod,
                'amount' => $amount,
                'received_amount' => $receivedAmount,
                'status' => $status,
                'reference_number' => $selectedMethod !== 'cash' ? $this->generateReferenceNumber($selectedMethod) : null,
                'processed_at' => $status === 'completed' ? $order->completed_at ?? $order->created_at : null,
                'paid_at' => $status === 'completed' ? $order->completed_at ?? $order->created_at : null,
                'gateway' => $this->getGatewayForMethod($selectedMethod),
                'gateway_fee' => $this->calculateGatewayFee($selectedMethod, $amount),
                'created_at' => $order->completed_at ?? $order->created_at,
                'updated_at' => $order->completed_at ?? $order->created_at,
            ]);

            $paymentCount++;
        }

        $this->command->info("✅ Created {$paymentCount} payments for orders successfully!");
    }

    /**
     * Generate reference number based on payment method.
     */
    private function generateReferenceNumber(string $method): string
    {
        $prefix = match ($method) {
            'qris' => 'QRIS',
            'e_wallet' => 'EWALLET',
            'debit_card', 'credit_card' => 'CARD',
            'bank_transfer' => 'TRF',
            default => 'REF',
        };

        return $prefix . now()->format('Ymd') . str_pad(rand(1, 9999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get gateway name for payment method.
     */
    private function getGatewayForMethod(string $method): ?string
    {
        return match ($method) {
            'qris', 'e_wallet', 'bank_transfer' => 'xendit',
            'credit_card', 'debit_card' => 'xendit',
            default => null,
        };
    }

    /**
     * Calculate gateway fee based on payment method.
     */
    private function calculateGatewayFee(string $method, float $amount): float
    {
        if ($method === 'cash') {
            return 0;
        }

        // Calculate percentage fee
        $feePercentage = match ($method) {
            'qris' => 0.005,        // 0.5%
            'e_wallet' => 0.007,    // 0.7%
            'bank_transfer' => 0.003, // 0.3%
            'debit_card', 'credit_card' => 0.015, // 1.5%
            default => 0,
        };

        return round($amount * $feePercentage, 2);
    }
}

