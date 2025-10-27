<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Store;

class LandingSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'company',
        'phone',
        'country',
        'preferred_contact_method',
        'notes',
        'plan',
        'status',
        'stage',
        'meta',
        'processed_at',
        'processed_by',
        'follow_up_logs',
        'provisioned_store_id',
        'provisioned_user_id',
        'provisioned_at',
        'onboarding_url',
        'xendit_invoice_id',
        'payment_status',
        'payment_amount',
        'paid_at',
        'subscription_id',
        'business_name',
        'business_type',
        'plan_id',
        'billing_cycle',
    ];

    protected $casts = [
        'meta' => 'array',
        'processed_at' => 'datetime',
        'follow_up_logs' => 'array',
        'provisioned_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_amount' => 'decimal:2',
    ];

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function provisionedStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'provisioned_store_id');
    }

    public function provisionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provisioned_user_id');
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
     * Get the activated subscription.
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
