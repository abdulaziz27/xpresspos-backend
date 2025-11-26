<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TenantAddOn extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'add_on_id',
        'quantity',
        'billing_cycle',
        'price',
        'status',
        'starts_at',
        'ends_at',
        'cancelled_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this add-on.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the add-on definition.
     */
    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }

    /**
     * Get the payments for this add-on.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(AddOnPayment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(AddOnPayment::class)->latestOfMany();
    }

    /**
     * Check if add-on is active.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // For monthly billing, check if not expired
        if ($this->billing_cycle === 'monthly') {
            return $this->ends_at === null || $this->ends_at->isFuture();
        }

        // For annual billing, check if not expired
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Get total additional limit provided by this add-on.
     */
    public function getTotalAdditionalLimit(): int
    {
        return $this->quantity * ($this->addOn->quantity ?? 0);
    }

    /**
     * Scope for active add-ons.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            });
    }
}
