<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\CoreApi;
use Midtrans\Snap;

class PaymentService
{
    public function __construct()
    {
        // Set Midtrans configuration
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.is_sanitized');
        Config::$is3ds = config('services.midtrans.is_3ds');
    }

    /**
     * Create or retrieve Midtrans customer for user
     */
    public function getOrCreateMidtransCustomer(User $user): array
    {
        // Check if user already has a Midtrans customer ID
        if ($user->midtrans_customer_id) {
            return [
                'id' => $user->midtrans_customer_id,
                'email' => $user->email,
                'name' => $user->name,
            ];
        }

        // Generate customer ID for Midtrans
        $customerId = 'customer_' . $user->id . '_' . time();
        
        // Save customer ID to user
        $user->update(['midtrans_customer_id' => $customerId]);

        return [
            'id' => $customerId,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }

    /**
     * Create payment token for Midtrans
     */
    public function createPaymentToken(User $user, array $paymentData): array
    {
        $customer = $this->getOrCreateMidtransCustomer($user);

        $params = [
            'transaction_details' => [
                'order_id' => 'setup_' . uniqid(),
                'gross_amount' => 1000, // Minimal amount for setup
            ],
            'customer_details' => [
                'first_name' => $customer['name'],
                'email' => $customer['email'],
                'customer_id' => $customer['id'],
            ],
            'enabled_payments' => $paymentData['enabled_payments'] ?? [
                'credit_card', 'bca_va', 'bni_va', 'bri_va', 'mandiri_va', 
                'permata_va', 'other_va', 'gopay', 'shopeepay', 'qris'
            ],
            'credit_card' => [
                'secure' => true,
                'save_card' => true,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);
            
            return [
                'snap_token' => $snapToken,
                'redirect_url' => config('services.midtrans.is_production') 
                    ? 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken
                    : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Midtrans payment token', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create payment token: ' . $e->getMessage());
        }
    }

    /**
     * Save payment method from Midtrans callback
     */
    public function savePaymentMethod(User $user, array $paymentData, bool $setAsDefault = false): PaymentMethod
    {
        try {
            // Extract payment method details from Midtrans callback
            $type = $this->determineMidtransPaymentType($paymentData);
            $lastFour = $this->extractLastFour($paymentData);
            $expiresAt = $this->extractExpiryDate($paymentData);
            $metadata = $this->extractMetadata($paymentData);

            return DB::transaction(function () use ($user, $paymentData, $type, $lastFour, $expiresAt, $metadata, $setAsDefault) {
                // Create payment method record
                $paymentMethod = PaymentMethod::create([
                    'user_id' => $user->id,
                    'gateway' => 'midtrans',
                    'gateway_id' => $paymentData['saved_token_id'] ?? $paymentData['order_id'] ?? uniqid(),
                    'type' => $type,
                    'last_four' => $lastFour,
                    'expires_at' => $expiresAt,
                    'is_default' => false,
                    'metadata' => $metadata,
                ]);

                // Set as default if requested or if it's the first payment method
                if ($setAsDefault || $user->paymentMethods()->count() === 1) {
                    $paymentMethod->setAsDefault();
                }

                return $paymentMethod;
            });

        } catch (\Exception $e) {
            Log::error('Failed to save payment method', [
                'user_id' => $user->id,
                'payment_data' => $paymentData,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to save payment method: ' . $e->getMessage());
        }
    }

    /**
     * Create payment transaction for invoice
     */
    public function createPaymentTransaction(Invoice $invoice, ?PaymentMethod $paymentMethod = null): array
    {
        try {
            $user = $invoice->subscription->store->users()->first();
            $customer = $this->getOrCreateMidtransCustomer($user);

            $params = [
                'transaction_details' => [
                    'order_id' => 'invoice_' . $invoice->id . '_' . time(),
                    'gross_amount' => (int) $invoice->total_amount,
                ],
                'customer_details' => [
                    'first_name' => $customer['name'],
                    'email' => $customer['email'],
                    'customer_id' => $customer['id'],
                ],
                'item_details' => [
                    [
                        'id' => 'subscription',
                        'price' => (int) $invoice->amount,
                        'quantity' => 1,
                        'name' => "Subscription Payment - Invoice #{$invoice->invoice_number}",
                    ],
                ],
                'enabled_payments' => [
                    'credit_card', 'bca_va', 'bni_va', 'bri_va', 'mandiri_va', 
                    'permata_va', 'other_va', 'gopay', 'shopeepay', 'qris'
                ],
            ];

            // Add tax if applicable
            if ($invoice->tax_amount > 0) {
                $params['item_details'][] = [
                    'id' => 'tax',
                    'price' => (int) $invoice->tax_amount,
                    'quantity' => 1,
                    'name' => 'Tax',
                ];
            }

            // Use saved payment method if provided
            if ($paymentMethod && $paymentMethod->gateway === 'midtrans') {
                $params['credit_card'] = [
                    'secure' => true,
                    'save_card' => false,
                    'saved_token_id' => $paymentMethod->gateway_id,
                ];
            }

            $snapToken = Snap::getSnapToken($params);
            
            return [
                'snap_token' => $snapToken,
                'order_id' => $params['transaction_details']['order_id'],
                'redirect_url' => config('services.midtrans.is_production') 
                    ? 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken
                    : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create payment transaction', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create payment transaction: ' . $e->getMessage());
        }
    }

    /**
     * Process payment for invoice
     */
    public function processPayment(Invoice $invoice, ?PaymentMethod $paymentMethod = null): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentMethod) {
            try {
                // Create payment transaction
                $transaction = $this->createPaymentTransaction($invoice, $paymentMethod);

                // Create payment record
                $payment = Payment::create([
                    'store_id' => $invoice->subscription->store_id,
                    'invoice_id' => $invoice->id,
                    'payment_method_id' => $paymentMethod?->id,
                    'gateway' => 'midtrans',
                    'gateway_transaction_id' => $transaction['order_id'],
                    'amount' => $invoice->total_amount,
                    'gateway_fee' => 0, // Will be updated from notification
                    'status' => 'pending',
                    'gateway_response' => $transaction,
                    'processed_at' => now(),
                ]);

                return $payment;

            } catch (\Exception $e) {
                Log::error('Payment processing failed', [
                    'invoice_id' => $invoice->id,
                    'payment_method_id' => $paymentMethod?->id,
                    'error' => $e->getMessage()
                ]);

                throw new \Exception('Payment processing failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Handle Midtrans notification
     */
    public function handleNotification(array $notification): bool
    {
        try {
            Log::info('Midtrans notification received', [
                'order_id' => $notification['order_id'] ?? null,
                'transaction_status' => $notification['transaction_status'] ?? null,
                'payment_type' => $notification['payment_type'] ?? null,
            ]);

            // Verify notification authenticity
            if (!$this->verifyNotification($notification)) {
                Log::warning('Invalid Midtrans notification signature');
                return false;
            }

            $orderId = $notification['order_id'];
            $transactionStatus = $notification['transaction_status'];
            $fraudStatus = $notification['fraud_status'] ?? null;

            // Find payment by gateway transaction ID
            $payment = Payment::where('gateway_transaction_id', $orderId)->first();

            if (!$payment) {
                Log::warning('Payment not found for order ID', ['order_id' => $orderId]);
                return false;
            }

            // Update payment status based on Midtrans status
            $this->updatePaymentStatus($payment, $transactionStatus, $fraudStatus, $notification);

            return true;

        } catch (\Exception $e) {
            Log::error('Notification handling failed', [
                'error' => $e->getMessage(),
                'notification' => $notification
            ]);

            return false;
        }
    }

    /**
     * Delete payment method
     */
    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        try {
            // For Midtrans, we just delete the local record
            // Saved tokens are managed by Midtrans automatically
            $paymentMethod->delete();

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to delete payment method', [
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Map Midtrans payment status to local status
     */
    private function mapMidtransStatus(string $transactionStatus, ?string $fraudStatus = null): string
    {
        if ($fraudStatus === 'deny') {
            return 'failed';
        }

        return match ($transactionStatus) {
            'capture', 'settlement' => 'completed',
            'pending' => 'pending',
            'deny', 'cancel', 'expire' => 'failed',
            'refund', 'partial_refund' => 'refunded',
            default => 'pending',
        };
    }



    /**
     * Verify Midtrans notification authenticity
     */
    private function verifyNotification(array $notification): bool
    {
        $orderId = $notification['order_id'] ?? '';
        $statusCode = $notification['status_code'] ?? '';
        $grossAmount = $notification['gross_amount'] ?? '';
        $serverKey = config('services.midtrans.server_key');
        
        $signatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        
        return $signatureKey === ($notification['signature_key'] ?? '');
    }

    /**
     * Update payment status based on Midtrans notification
     */
    private function updatePaymentStatus(Payment $payment, string $transactionStatus, ?string $fraudStatus, array $notification): void
    {
        $newStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);
        
        $payment->update([
            'status' => $newStatus,
            'gateway_response' => array_merge($payment->gateway_response ?? [], $notification),
        ]);

        // Update invoice status
        if ($payment->invoice) {
            if ($newStatus === 'completed') {
                $payment->invoice->markAsPaid();
            } elseif (in_array($newStatus, ['failed', 'cancelled'])) {
                $payment->invoice->markAsFailed();
            }
        }
    }

    /**
     * Determine payment type from Midtrans data
     */
    private function determineMidtransPaymentType(array $paymentData): string
    {
        $paymentType = $paymentData['payment_type'] ?? '';
        
        return match ($paymentType) {
            'credit_card' => 'card',
            'bank_transfer' => 'bank_transfer',
            'echannel' => 'va',
            'gopay', 'shopeepay' => 'digital_wallet',
            'qris' => 'qris',
            default => 'other',
        };
    }

    /**
     * Extract last four digits from payment data
     */
    private function extractLastFour(array $paymentData): ?string
    {
        if (isset($paymentData['masked_card'])) {
            return substr($paymentData['masked_card'], -4);
        }
        
        if (isset($paymentData['va_numbers'][0]['va_number'])) {
            return substr($paymentData['va_numbers'][0]['va_number'], -4);
        }
        
        return null;
    }

    /**
     * Extract expiry date from payment data
     */
    private function extractExpiryDate(array $paymentData): ?\Carbon\Carbon
    {
        if (isset($paymentData['card_type']) && $paymentData['card_type'] === 'credit') {
            // For credit cards, we don't get expiry from Midtrans notification
            // Set a default expiry of 4 years from now
            return now()->addYears(4);
        }
        
        return null;
    }

    /**
     * Extract metadata from payment data
     */
    private function extractMetadata(array $paymentData): array
    {
        $metadata = [];
        
        if (isset($paymentData['payment_type'])) {
            $metadata['payment_type'] = $paymentData['payment_type'];
        }
        
        if (isset($paymentData['bank'])) {
            $metadata['bank'] = $paymentData['bank'];
        }
        
        if (isset($paymentData['card_type'])) {
            $metadata['card_type'] = $paymentData['card_type'];
        }
        
        if (isset($paymentData['saved_token_id'])) {
            $metadata['saved_token_id'] = $paymentData['saved_token_id'];
        }
        
        return $metadata;
    }
}