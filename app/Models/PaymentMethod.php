<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'gateway',
        'gateway_id',
        'type',
        'last_four',
        'expires_at',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the payment method.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payments for this payment method.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if payment method is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get display name for payment method.
     */
    public function getDisplayNameAttribute(): string
    {
        $type = ucfirst($this->type);
        
        if ($this->last_four) {
            return "{$type} ending in {$this->last_four}";
        }
        
        return $type;
    }

    /**
     * Get masked card number for display.
     */
    public function getMaskedNumberAttribute(): string
    {
        if ($this->last_four) {
            return "**** **** **** {$this->last_four}";
        }
        
        return 'N/A';
    }

    /**
     * Check if payment method can be used for payments.
     */
    public function isUsable(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Set this payment method as default for the user.
     */
    public function setAsDefault(): void
    {
        // Remove default from other payment methods
        $this->user->paymentMethods()->update(['is_default' => false]);
        
        // Set this as default
        $this->update(['is_default' => true]);
    }

    /**
     * Scope to get default payment method.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get usable payment methods.
     */
    public function scopeUsable($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to filter by gateway.
     */
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}