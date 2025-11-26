<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddOn extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'feature_code',
        'quantity',
        'price_monthly',
        'price_annual',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get tenant add-ons that use this add-on.
     */
    public function tenantAddOns(): HasMany
    {
        return $this->hasMany(TenantAddOn::class);
    }

    /**
     * Get active tenant add-ons.
     */
    public function activeTenantAddOns(): HasMany
    {
        return $this->hasMany(TenantAddOn::class)->where('status', 'active');
    }

    /**
     * Scope for active add-ons.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get price based on billing cycle.
     */
    public function getPrice(string $billingCycle): float
    {
        return $billingCycle === 'annual' ? (float) $this->price_annual : (float) $this->price_monthly;
    }
}
