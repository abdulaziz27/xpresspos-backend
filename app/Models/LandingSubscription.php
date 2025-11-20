<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\Plan;

class LandingSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        // Authenticated checkout fields (NEW - untuk flow wajib login)
        'user_id',
        'tenant_id',
        'plan_id',
        'billing_cycle',
        
        // Status & stage tracking
        'status',
        'stage',
        'payment_status',
        'payment_amount',
        'paid_at',
        
        // Links ke entity yang dibuat setelah provisioning
        'subscription_id',
        'provisioned_store_id',
        'provisioned_user_id',
        'provisioned_at',
        
        // Payment tracking
        'xendit_invoice_id',
        
        // Legacy fields (untuk backward compatibility, bisa dihapus nanti)
        'email',
        'name',
        'company',
        'phone',
        'country',
        'preferred_contact_method',
        'notes',
        'plan', // Legacy string plan name
        
        // Metadata & tracking
        'meta',
        'follow_up_logs',
        'processed_at',
        'processed_by',
        'onboarding_url',
        'business_name',
        'business_type',
        
        // Upgrade/Downgrade tracking
        'is_upgrade',
        'is_downgrade',
        'previous_plan_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'processed_at' => 'datetime',
        'follow_up_logs' => 'array',
        'provisioned_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_amount' => 'decimal:2',
        'is_upgrade' => 'boolean',
        'is_downgrade' => 'boolean',
    ];

    /**
     * Get the user who initiated this checkout.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant for this checkout.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the plan for this checkout.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the previous plan (for upgrade/downgrade tracking).
     */
    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    /**
     * Get the activated subscription (setelah provisioning).
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the subscription payments for this landing subscription.
     */
    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Get the latest subscription payment.
     */
    public function latestSubscriptionPayment()
    {
        return $this->hasOne(SubscriptionPayment::class)->latest();
    }

    /**
     * Get the user who processed this (legacy field).
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the provisioned store (setelah provisioning).
     */
    public function provisionedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'provisioned_store_id');
    }

    /**
     * Get the provisioned user (setelah provisioning, legacy field).
     */
    public function provisionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provisioned_user_id');
    }

    /**
     * Check if this checkout is for authenticated user (has user_id and tenant_id).
     */
    public function isAuthenticated(): bool
    {
        return $this->user_id !== null && $this->tenant_id !== null;
    }

    /**
     * Check if this checkout is for anonymous user (legacy flow).
     */
    public function isAnonymous(): bool
    {
        return $this->user_id === null || $this->tenant_id === null;
    }

    /**
     * Check if this is a plan upgrade.
     */
    public function isUpgrade(): bool
    {
        return $this->is_upgrade === true;
    }

    /**
     * Check if this is a plan downgrade.
     */
    public function isDowngrade(): bool
    {
        return $this->is_downgrade === true;
    }

    /**
     * Check if this is a plan change (upgrade or downgrade).
     */
    public function isPlanChange(): bool
    {
        return $this->is_upgrade || $this->is_downgrade;
    }

    /**
     * Get the change type (new, upgrade, downgrade).
     */
    public function getChangeType(): string
    {
        if ($this->is_upgrade) {
            return 'upgrade';
        }
        if ($this->is_downgrade) {
            return 'downgrade';
        }
        return 'new';
    }
}
