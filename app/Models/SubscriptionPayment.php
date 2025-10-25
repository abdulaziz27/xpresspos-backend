<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'landing_subscription_id',
        'subscription_id',
        'invoice_id',
        'xendit_invoice_id',
        'external_id',
        'payment_method',
        'payment_channel',
        'amount',
        'gateway_fee',
        'status',
        'gateway_response',
        'paid_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the landing subscription that owns this payment.
     */
    public function landingSubscription(): BelongsTo
    {
        return $this->belongsTo(LandingSubscription::class);
    }

    /**
     * Get the subscription that owns this payment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the invoice that this payment is for.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Check if payment is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' && $this->paid_at !== null;
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment has expired.
     */
    public function hasExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Check if payment has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Mark payment as paid.
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /**
     * Mark payment as expired.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Update payment with Xendit callback data.
     */
    public function updateFromXenditCallback(array $callbackData): void
    {
        $updateData = [
            'gateway_response' => $callbackData,
        ];

        // Update status based on Xendit status
        if (isset($callbackData['status'])) {
            $updateData['status'] = $this->mapXenditStatus($callbackData['status']);
        }

        // Update paid_at if payment is completed
        if (isset($callbackData['paid_at']) && $callbackData['status'] === 'PAID') {
            $updateData['paid_at'] = $callbackData['paid_at'];
        }

        // Update payment method and channel if available
        if (isset($callbackData['payment_method'])) {
            $updateData['payment_method'] = $this->mapXenditPaymentMethod($callbackData['payment_method']);
        }

        if (isset($callbackData['payment_channel'])) {
            $updateData['payment_channel'] = $callbackData['payment_channel'];
        }

        // Update gateway fee if available
        if (isset($callbackData['fees'])) {
            $updateData['gateway_fee'] = collect($callbackData['fees'])->sum('value');
        }

        $this->update($updateData);
    }

    /**
     * Map Xendit status to our payment status.
     */
    private function mapXenditStatus(string $xenditStatus): string
    {
        return match($xenditStatus) {
            'PAID' => 'paid',
            'PENDING' => 'pending',
            'EXPIRED' => 'expired',
            'FAILED' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Map Xendit payment method to our payment method enum.
     */
    private function mapXenditPaymentMethod(string $xenditMethod): string
    {
        return match(strtoupper($xenditMethod)) {
            'BANK_TRANSFER' => 'bank_transfer',
            'EWALLET', 'OVO', 'DANA', 'LINKAJA', 'SHOPEEPAY' => 'e_wallet',
            'QR_CODE', 'QRIS' => 'qris',
            'CREDIT_CARD', 'DEBIT_CARD' => 'credit_card',
            default => 'bank_transfer'
        };
    }

    /**
     * Get payment method display name.
     */
    public function getPaymentMethodDisplayName(): string
    {
        if ($this->payment_channel) {
            return strtoupper($this->payment_channel);
        }

        return ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    /**
     * Get net amount after gateway fees.
     */
    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->gateway_fee;
    }

    /**
     * Get days until expiration.
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return now()->diffInDays($this->expires_at, false);
    }

    /**
     * Scope to get paid payments.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get expired payments.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope to get failed payments.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get payments by method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope to get payments by Xendit invoice ID.
     */
    public function scopeByXenditInvoiceId($query, string $xenditInvoiceId)
    {
        return $query->where('xendit_invoice_id', $xenditInvoiceId);
    }

    /**
     * Scope to get payments by external ID.
     */
    public function scopeByExternalId($query, string $externalId)
    {
        return $query->where('external_id', $externalId);
    }

    /**
     * Scope to get payments expiring soon.
     */
    public function scopeExpiringSoon($query, int $hours = 24)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '<=', now()->addHours($hours))
                    ->where('expires_at', '>', now());
    }

    /**
     * Generate unique external ID for Xendit.
     */
    public static function generateExternalId(): string
    {
        $prefix = 'SUB';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        
        return "{$prefix}-{$timestamp}-{$random}";
    }
}