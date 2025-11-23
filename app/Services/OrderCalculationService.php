<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

class OrderCalculationService
{
    /**
     * Calculate order totals with single source of truth.
     */
    public function calculateOrderTotals(Order $order): array
    {
        $subtotal = 0;
        
        // Calculate subtotal from all items
        foreach ($order->items as $item) {
            $subtotal += $this->calculateItemTotal($item);
        }
        
        // Calculate tax (configurable per store, default 0)
        // Ensure store is loaded
        if (!$order->relationLoaded('store')) {
            $order->load('store');
        }
        
        $settings = $order->store->settings ?? [];
        $taxRate = isset($settings['tax_rate']) ? (float) $settings['tax_rate'] : 0;
        $taxIncluded = isset($settings['tax_included']) ? (bool) $settings['tax_included'] : false;
        
        // Convert tax rate from percentage to decimal (e.g., 10% -> 0.10)
        $taxRateDecimal = $taxRate / 100;
        
        if ($taxRateDecimal == 0) {
            $taxAmount = 0;
        } else {
            if ($taxIncluded) {
                // Tax sudah termasuk dalam harga
                // Formula: tax = subtotal - (subtotal / (1 + tax_rate))
                $taxAmount = $subtotal - ($subtotal / (1 + $taxRateDecimal));
                $subtotal = $subtotal - $taxAmount;
            } else {
                // Tax ditambahkan ke harga
                $taxAmount = $subtotal * $taxRateDecimal;
            }
        }
        
        // Service charge (from order or default 0)
        $serviceCharge = $order->service_charge ?? 0;
        
        // Discount amount (from order or default 0)
        $discountAmount = $order->discount_amount ?? 0;
        
        // Calculate total
        $totalAmount = $subtotal + $taxAmount + $serviceCharge - $discountAmount;
        
        // Ensure total is not negative
        $totalAmount = max(0, $totalAmount);
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'service_charge' => round($serviceCharge, 2),
            'discount_amount' => round($discountAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }
    
    /**
     * Calculate individual item total with options.
     */
    public function calculateItemTotal(OrderItem $item): float
    {
        $basePrice = $item->unit_price;
        $optionsPrice = 0;
        
        // Calculate options price adjustment
        if ($item->product_options && is_array($item->product_options)) {
            foreach ($item->product_options as $option) {
                $optionsPrice += $option['price_adjustment'] ?? 0;
            }
        }
        
        $unitPriceWithOptions = $basePrice + $optionsPrice;
        $totalPrice = $unitPriceWithOptions * $item->quantity;
        
        return round($totalPrice, 2);
    }
    
    /**
     * Calculate price with product options before adding to order.
     */
    public function calculateProductPriceWithOptions(Product $product, array $selectedOptions = []): array
    {
        $basePrice = $product->price;
        $optionsPrice = 0;
        $processedOptions = [];
        
        if (!empty($selectedOptions) && $product->options) {
            foreach ($selectedOptions as $optionId => $selectedValue) {
                $productOption = $product->options->find($optionId);
                
                if ($productOption) {
                    $optionsPrice += $productOption->price_adjustment ?? 0;
                    $processedOptions[] = [
                        'option_id' => $productOption->id,
                        'name' => $productOption->name,
                        'value' => $selectedValue,
                        'price_adjustment' => $productOption->price_adjustment ?? 0,
                    ];
                }
            }
        }
        
        return [
            'base_price' => $basePrice,
            'options_price' => $optionsPrice,
            'total_price' => $basePrice + $optionsPrice,
            'selected_options' => $processedOptions,
        ];
    }
    
    /**
     * Update order totals and save to database.
     */
    public function updateOrderTotals(Order $order): Order
    {
        // Ensure store relationship is loaded for tax calculation
        if (!$order->relationLoaded('store')) {
            $order->load('store');
        }
        
        // Ensure items are loaded for subtotal calculation
        if (!$order->relationLoaded('items')) {
            $order->load('items');
        }
        
        $calculations = $this->calculateOrderTotals($order);
        
        $order->update($calculations);
        
        // Note: Payment status is calculated dynamically, no need to store in DB
        
        return $order->fresh();
    }
    
    /**
     * Get order payment status based on payments (without updating database).
     */
    public function getOrderPaymentStatus(Order $order): string
    {
        $totalAmount = $order->total_amount;
        $paidAmount = $order->payments()->where('status', 'completed')->sum('amount');
        $refundedAmount = $order->refunds()->where('status', 'completed')->sum('amount');
        
        $netPaidAmount = $paidAmount - $refundedAmount;
        
        if ($refundedAmount > 0 && $netPaidAmount <= 0) {
            return 'refunded';
        } elseif ($netPaidAmount >= $totalAmount) {
            return 'paid';
        } elseif ($netPaidAmount > 0) {
            return 'partial';
        }
        
        return 'unpaid';
    }
    
    /**
     * Calculate remaining balance for order.
     */
    public function calculateRemainingBalance(Order $order): float
    {
        $totalAmount = $order->total_amount;
        $paidAmount = $order->payments()->where('status', 'completed')->sum('amount');
        $refundedAmount = $order->refunds()->where('status', 'completed')->sum('amount');
        
        $remainingBalance = $totalAmount - ($paidAmount - $refundedAmount);
        
        return max(0, round($remainingBalance, 2));
    }
    
    /**
     * Validate if payment amount is valid for order.
     */
    public function validatePaymentAmount(Order $order, float $paymentAmount): array
    {
        $remainingBalance = $this->calculateRemainingBalance($order);
        
        if ($paymentAmount <= 0) {
            return [
                'valid' => false,
                'error' => 'Payment amount must be greater than zero',
            ];
        }
        
        if ($paymentAmount > $remainingBalance) {
            return [
                'valid' => false,
                'error' => 'Payment amount exceeds remaining balance',
                'remaining_balance' => $remainingBalance,
                'requested_amount' => $paymentAmount,
            ];
        }
        
        return [
            'valid' => true,
            'remaining_balance' => $remainingBalance,
            'requested_amount' => $paymentAmount,
            'new_remaining_balance' => $remainingBalance - $paymentAmount,
        ];
    }
}