<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddOnPayment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_add_on_id',
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
        'invoice_url',
        'last_reminder_sent_at',
        'reminder_count',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'reminder_count' => 'integer',
    ];

    /**
     * Get the tenant add-on that owns this payment.
     */
    public function tenantAddOn(): BelongsTo
    {
        return $this->belongsTo(TenantAddOn::class);
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
     * Update payment from Xendit callback.
     */
    public function updateFromXenditCallback(array $payload): void
    {
        $this->update([
            'status' => $this->mapXenditStatus($payload['status'] ?? 'pending'),
            'payment_method' => $payload['payment_method'] ?? null,
            'payment_channel' => $payload['payment_channel'] ?? null,
            'gateway_fee' => $payload['fees'] ?? 0,
            'gateway_response' => $payload,
            'paid_at' => isset($payload['paid_at']) ? \Carbon\Carbon::parse($payload['paid_at']) : null,
            'expires_at' => isset($payload['expiry_date']) ? \Carbon\Carbon::parse($payload['expiry_date']) : null,
        ]);
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
     * Map Xendit status to our status enum.
     */
    protected function mapXenditStatus(string $xenditStatus): string
    {
        return match (strtolower($xenditStatus)) {
            'paid', 'settled' => 'paid',
            'expired' => 'expired',
            'failed', 'voided' => 'failed',
            default => 'pending',
        };
    }
}
