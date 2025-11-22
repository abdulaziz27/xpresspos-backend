<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Enums\PaymentMethodEnum;

class PaymentValidationService
{
    /**
     * Validate if payment can be processed for order.
     */
    public function validatePayment(Order $order, array $paymentData): array
    {
        // Skip validation for pending payments (open bill placeholder)
        if (isset($paymentData['payment_method']) && $paymentData['payment_method'] === 'pending') {
            return [
                'valid' => true,
                'errors' => [],
                'remaining_balance' => $order->total_amount,
                'requested_amount' => $paymentData['amount'] ?? 0,
            ];
        }
        
        $errors = [];
        
        // Check order status
        if ($order->status === 'draft') {
            $errors[] = 'Cannot process payment for draft orders. Please set order to open status first.';
        }
        
        if ($order->status === 'cancelled') {
            $errors[] = 'Cannot process payment for cancelled orders.';
        }
        
        // Check payment method
        $validMethods = array_column(PaymentMethodEnum::getAll(), 'id');
        if (!in_array($paymentData['payment_method'], $validMethods)) {
            $errors[] = 'Invalid payment method: ' . $paymentData['payment_method'];
        }
        
        // Check payment amount
        $remainingBalance = $this->calculateRemainingBalance($order);
        $paymentMethod = $paymentData['payment_method'] ?? null;
        $isCashPayment = $paymentMethod === 'cash';
        $requestedAmount = $paymentData['amount'];
        $receivedAmount = $paymentData['received_amount'] ?? null;
        
        if ($requestedAmount <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        }
        
        // For cash payments: allow overpayment (amount can be >= remaining_balance)
        // The controller will adjust: actual payment amount = min(amount, remaining_balance)
        // received_amount will be used for change calculation
        if ($isCashPayment) {
            // For cash, amount should be at least the remaining balance
            // Overpayment is allowed (will be handled as change)
            if ($requestedAmount < $remainingBalance) {
                $errors[] = sprintf(
                    'Payment amount (%.2f) is less than remaining balance (%.2f). For cash payments, amount must be at least the remaining balance.',
                    $requestedAmount,
                    $remainingBalance
                );
            }
            // Overpayment is allowed for cash - no error if amount > remaining_balance
        } else {
            // For non-cash payments, amount must match remaining balance exactly
            if (abs($requestedAmount - $remainingBalance) > 0.01) { // Allow small rounding differences
                $errors[] = sprintf(
                    'Payment amount (%.2f) does not match remaining balance (%.2f). For non-cash payments, amount must match the remaining balance exactly.',
                    $requestedAmount,
                    $remainingBalance
                );
            }
        }
        
        // Check if order is already fully paid
        if ($remainingBalance <= 0) {
            $errors[] = 'Order is already fully paid.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'remaining_balance' => $remainingBalance,
            'requested_amount' => $paymentData['amount'],
        ];
    }
    
    /**
     * Calculate remaining balance for order.
     */
    private function calculateRemainingBalance(Order $order): float
    {
        // Ensure order has items loaded
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }
        
        // Ensure order has store loaded (needed for tax calculation)
        if (!$order->relationLoaded('store')) {
            $order->load('store');
        }
        
        // Recalculate totals if order has items but total_amount is 0 or null
        if ($order->items->count() > 0 && (!$order->total_amount || $order->total_amount == 0)) {
            \Log::info('Recalculating order totals in PaymentValidationService', [
                'order_id' => $order->id,
                'items_count' => $order->items->count(),
                'current_total' => $order->total_amount,
            ]);
            
            $calculationService = app(\App\Services\OrderCalculationService::class);
            $calculationService->updateOrderTotals($order);
            $order->refresh();
            
            \Log::info('Order totals recalculated', [
                'order_id' => $order->id,
                'new_total' => $order->total_amount,
            ]);
        }
        
        $totalAmount = $order->total_amount ?? 0;
        $paidAmount = $order->payments()->where('status', 'completed')->sum('amount');
        $refundedAmount = $order->refunds()->where('status', 'completed')->sum('amount');
        
        $remainingBalance = $totalAmount - ($paidAmount - $refundedAmount);
        
        \Log::info('Payment balance calculation', [
            'order_id' => $order->id,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'refunded_amount' => $refundedAmount,
            'remaining_balance' => $remainingBalance,
        ]);
        
        return max(0, round($remainingBalance, 2));
    }
    
    /**
     * Validate refund request.
     */
    public function validateRefund(Payment $payment, float $refundAmount): array
    {
        $errors = [];
        
        if ($payment->status !== 'completed') {
            $errors[] = 'Can only refund completed payments.';
        }
        
        $refundableAmount = $payment->getRefundableAmount();
        if ($refundAmount > $refundableAmount) {
            $errors[] = 'Refund amount exceeds refundable amount.';
        }
        
        if ($refundAmount <= 0) {
            $errors[] = 'Refund amount must be greater than zero.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'refundable_amount' => $refundableAmount,
            'requested_amount' => $refundAmount,
        ];
    }
}