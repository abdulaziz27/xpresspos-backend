<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'plan_id',
        'feature_code',
        'limit_value',
        'is_enabled',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the plan that owns the feature.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if the feature is unlimited.
     */
    public function isUnlimited(): bool
    {
        return is_null($this->limit_value);
    }

    /**
     * Get the numeric limit value.
     */
    public function getNumericLimit(): ?int
    {
        if ($this->isUnlimited()) {
            return null;
        }

        return is_numeric($this->limit_value) ? (int) $this->limit_value : null;
    }
}
