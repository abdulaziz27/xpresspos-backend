<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class Order extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'member_id',
        'table_id',
        'order_number',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'service_charge',
        'total_amount',
        'total_items',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_items' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Get the user who created the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the member associated with the order.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the table associated with the order.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Get the order items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payments for the order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the refunds for the order.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Generate unique order number.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', now())->count() + 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate order totals using OrderCalculationService.
     */
    public function calculateTotals(): void
    {
        $calculationService = app(\App\Services\OrderCalculationService::class);
        $calculationService->updateOrderTotals($this);
    }

    /**
     * Mark order as completed.
     */
    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update member stats if applicable
        if ($this->member_id && $this->relationLoaded('member') && $this->member) {
            if (method_exists($this->member, 'updateStats')) {
                $this->member->updateStats($this->total_amount);
            }
        }

        // Make table available if applicable
        if ($this->table_id && $this->relationLoaded('table') && $this->table) {
            if (method_exists($this->table, 'makeAvailable')) {
                $this->table->makeAvailable();
            }
        }
    }

    /**
     * Check if order can be modified.
     */
    public function canBeModified(): bool
    {
        return in_array($this->status, ['draft', 'open']);
    }

    /**
     * Scope to get orders by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get orders for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now());
    }
    
    /**
     * Get the remaining balance for this order.
     */
    public function getRemainingBalance(): float
    {
        $calculationService = app(\App\Services\OrderCalculationService::class);
        return $calculationService->calculateRemainingBalance($this);
    }
    
    /**
     * Check if the order is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->getRemainingBalance() <= 0;
    }
    
    /**
     * Get the total amount paid for this order.
     */
    public function getTotalPaid(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }
    
    /**
     * Get the total amount refunded for this order.
     */
    public function getTotalRefunded(): float
    {
        return $this->refunds()->where('status', 'completed')->sum('amount');
    }
    
    /**
     * Get the current payment status of the order.
     */
    public function getPaymentStatus(): string
    {
        $calculationService = app(\App\Services\OrderCalculationService::class);
        return $calculationService->getOrderPaymentStatus($this);
    }
    
    /**
     * Get payment status display.
     */
    public function getPaymentStatusDisplay(): string
    {
        $remainingBalance = $this->getRemainingBalance();
        $totalPaid = $this->getTotalPaid();
        $totalRefunded = $this->getTotalRefunded();
        
        if ($totalRefunded > 0 && $remainingBalance >= $this->total_amount) {
            return 'Refunded';
        } elseif ($remainingBalance <= 0) {
            return 'Fully Paid';
        } elseif ($totalPaid > 0) {
            return 'Partially Paid';
        }
        
        return 'Unpaid';
    }
}
