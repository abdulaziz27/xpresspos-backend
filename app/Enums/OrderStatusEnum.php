<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canBeModified(): bool
    {
        return in_array($this, [self::DRAFT, self::OPEN]);
    }

    public function canAcceptPayments(): bool
    {
        return in_array($this, [self::OPEN, self::COMPLETED]);
    }
}

enum PaymentStatusEnum: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Fully Paid',
            self::REFUNDED => 'Refunded',
        };
    }

    /**
     * Get payment status from order calculations.
     */
    public static function fromOrder(\App\Models\Order $order): self
    {
        $calculationService = app(\App\Services\OrderCalculationService::class);
        $status = $calculationService->getOrderPaymentStatus($order);
        
        return self::from($status);
    }
}