<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class Payment extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'order_id', // Required for POS customer transactions
        'payment_method', // Enum: cash, credit_card, debit_card, qris, bank_transfer, e_wallet (match migration)
        'amount',
        'reference_number',
        'status',
        'processed_at',
        'notes',
        'gateway',
        'gateway_transaction_id',
        'payment_method_id', // Optional: Link to saved PaymentMethod for recurring payments
        'gateway_fee',
        'gateway_response',
        // NOTE: No invoice_id, xendit_invoice_id, or external_id - these are for subscription payments only
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'processed_at' => 'datetime',
        'gateway_response' => 'array',
    ];



    /**
     * Get the order that owns the payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the refunds for the payment.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Get the payment method used for this payment.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Check if this is a store order payment (always true for this model).
     */
    public function isOrderPayment(): bool
    {
        return !empty($this->order_id);
    }

    /**
     * Get payment method display name.
     */
    public function getPaymentMethodDisplayName(): string
    {
        return ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    /**
     * Mark payment as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Check if payment can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === 'completed' && 
               $this->refunds()->sum('amount') < $this->amount;
    }

    /**
     * Get remaining refundable amount.
     */
    public function getRefundableAmount(): float
    {
        return $this->amount - $this->refunds()->sum('amount');
    }

    /**
     * Scope to get payments by method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope to get completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get order payments (all payments in this model are order payments).
     */
    public function scopeOrderPayments($query)
    {
        return $query->whereNotNull('order_id');
    }

    /**
     * Get net amount after gateway fees.
     */
    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->gateway_fee;
    }

    /**
     * Check if payment was processed through a gateway.
     */
    public function hasGateway(): bool
    {
        return !empty($this->gateway);
    }

    /**
     * Scope to filter by gateway.
     */
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope to get payments with gateway fees.
     */
    public function scopeWithGatewayFees($query)
    {
        return $query->where('gateway_fee', '>', 0);
    }
}
