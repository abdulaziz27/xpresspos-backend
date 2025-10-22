<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscription_usage';

    protected $fillable = [
        'subscription_id',
        'feature_type',
        'current_usage',
        'annual_quota',
        'subscription_year_start',
        'subscription_year_end',
        'soft_cap_triggered',
        'soft_cap_triggered_at',
    ];

    protected $casts = [
        'subscription_year_start' => 'date',
        'subscription_year_end' => 'date',
        'soft_cap_triggered' => 'boolean',
        'soft_cap_triggered_at' => 'datetime',
    ];

    /**
     * Get the subscription that owns the usage record.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(int $amount = 1): void
    {
        $this->increment('current_usage', $amount);
        
        // Check if soft cap should be triggered
        if ($this->shouldTriggerSoftCap()) {
            $this->triggerSoftCap();
        }
    }

    /**
     * Check if usage has exceeded quota.
     */
    public function hasExceededQuota(): bool
    {
        return $this->annual_quota && $this->current_usage >= $this->annual_quota;
    }

    /**
     * Get usage percentage.
     */
    public function getUsagePercentage(): float
    {
        if (!$this->annual_quota) {
            return 0.0;
        }

        return round(($this->current_usage / $this->annual_quota) * 100, 2);
    }

    /**
     * Check if soft cap should be triggered (80% of quota).
     */
    public function shouldTriggerSoftCap(): bool
    {
        return !$this->soft_cap_triggered && 
               $this->annual_quota && 
               $this->getUsagePercentage() >= 80.0;
    }

    /**
     * Trigger soft cap warning.
     */
    public function triggerSoftCap(): void
    {
        $this->update([
            'soft_cap_triggered' => true,
            'soft_cap_triggered_at' => now(),
        ]);
    }

    /**
     * Reset usage for new subscription year.
     */
    public function resetForNewYear(): void
    {
        $this->update([
            'current_usage' => 0,
            'soft_cap_triggered' => false,
            'soft_cap_triggered_at' => null,
            'subscription_year_start' => now()->startOfYear(),
            'subscription_year_end' => now()->endOfYear(),
        ]);
    }

    /**
     * Scope to get usage records that have triggered soft cap.
     */
    public function scopeSoftCapTriggered($query)
    {
        return $query->where('soft_cap_triggered', true);
    }

    /**
     * Scope to get usage records by feature type.
     */
    public function scopeByFeature($query, string $featureType)
    {
        return $query->where('feature_type', $featureType);
    }
}
