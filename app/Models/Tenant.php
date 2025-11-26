<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'settings',
        'status',
        'plan_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the stores for the tenant.
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Get the current plan for the tenant.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the users that have access to this tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_tenant_access')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the subscriptions for the tenant.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the active subscription for the tenant.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->whereDate('ends_at', '>', today())
            ->with('plan')
            ->latest('created_at')
            ->first();
    }

    /**
     * Get the add-ons purchased by this tenant.
     */
    public function tenantAddOns(): HasMany
    {
        return $this->hasMany(TenantAddOn::class);
    }

    /**
     * Get active add-ons for this tenant.
     */
    public function activeAddOns(): HasMany
    {
        return $this->hasMany(TenantAddOn::class)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            });
    }
}
