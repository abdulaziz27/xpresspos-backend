<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'annual_price',
        'features',
        'limits',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'annual_price' => 'decimal:2',
    ];

    /**
     * Get the subscriptions for the plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the plan features for the plan.
     */
    public function planFeatures(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * Check if plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $featureCode = \App\Support\PlanFeatureResolver::normalizeFeatureCode($feature);

        if ($featureCode) {
            return $this->planFeatures()
                ->where('feature_code', $featureCode)
                ->where('is_enabled', true)
                ->exists();
        }

        // Fallback to legacy JSON column for features that belum dinormalisasi.
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Get limit for a specific feature.
     */
    public function getLimit(string $feature): ?int
    {
        $featureCode = \App\Support\PlanFeatureResolver::normalizeLimitCode($feature);

        if ($featureCode) {
            $planFeature = $this->planFeatures()
                ->where('feature_code', $featureCode)
                ->first();

            if (!$planFeature || !$planFeature->is_enabled) {
                return null;
            }

            if (is_null($planFeature->limit_value)) {
                return -1;
            }

            $limit = (int) $planFeature->limit_value;

            return $limit <= 0 ? -1 : $limit;
        }

        // Fallback ke legacy JSON limit.
        return $this->limits[$feature] ?? null;
    }

    /**
     * Get required plan for a feature.
     */
    public function getRequiredPlanFor(string $feature): string
    {
        // Define feature requirements
        $featureRequirements = [
            'inventory_tracking' => 'Pro',
            'cogs_calculation' => 'Pro',
            'multi_outlet' => 'Enterprise',
            'advanced_reports' => 'Pro',
            'report_export' => 'Pro',
            'monthly_email_reports' => 'Pro',
        ];

        return $featureRequirements[$feature] ?? 'Basic';
    }

    /**
     * Scope to get active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
