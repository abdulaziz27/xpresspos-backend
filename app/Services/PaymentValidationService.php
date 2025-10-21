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
        if ($paymentData['amount'] > $remainingBalance) {
            $errors[] = 'Payment amount exceeds remaining balance.';
        }
        
        if ($paymentData['amount'] <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
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
        $totalAmount = $order->total_amount;
        $paidAmount = $order->payments()->where('status', 'completed')->sum('amount');
        $refundedAmount = $order->refunds()->where('status', 'completed')->sum('amount');
        
        $remainingBalance = $totalAmount - ($paidAmount - $refundedAmount);
        
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