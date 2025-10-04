<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class StaffPerformance extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'date',
        'orders_processed',
        'total_sales',
        'average_order_value',
        'refunds_processed',
        'refund_amount',
        'hours_worked',
        'sales_per_hour',
        'customer_interactions',
        'customer_satisfaction_score',
        'additional_metrics',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'sales_per_hour' => 'decimal:2',
        'customer_satisfaction_score' => 'decimal:2',
        'additional_metrics' => 'array',
    ];

    /**
     * Get the store that owns the performance record.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the user (staff member) this performance record belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate and update performance metrics.
     */
    public function calculateMetrics(): void
    {
        if ($this->orders_processed > 0) {
            $this->average_order_value = $this->total_sales / $this->orders_processed;
        }

        if ($this->hours_worked > 0) {
            $this->sales_per_hour = $this->total_sales / $this->hours_worked;
        }

        $this->save();
    }

    /**
     * Scope for date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope for specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get performance summary for a user over a date range.
     */
    public static function getSummary($userId, $startDate, $endDate)
    {
        return static::forUser($userId)
            ->dateRange($startDate, $endDate)
            ->selectRaw('
                SUM(orders_processed) as total_orders,
                SUM(total_sales) as total_sales,
                AVG(average_order_value) as avg_order_value,
                SUM(refunds_processed) as total_refunds,
                SUM(refund_amount) as total_refund_amount,
                SUM(hours_worked) as total_hours,
                AVG(sales_per_hour) as avg_sales_per_hour,
                SUM(customer_interactions) as total_interactions,
                AVG(customer_satisfaction_score) as avg_satisfaction
            ')
            ->first();
    }
}
