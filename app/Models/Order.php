<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',           // Staff who created the order
        'member_id',         // Customer (can be member or guest)
        'customer_name',     // Customer name for receipt
        'customer_type',     // 'member', 'guest', 'walk_in'
        'table_id',
        'order_number',
        'operation_mode',    // 'dine_in', 'takeaway', 'delivery'
        'payment_mode',      // 'direct', 'open_bill'
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'service_charge',
        'total_amount',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',

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
            
            // Auto-set tenant_id from store
            if (!$order->tenant_id && $order->store_id) {
                $store = Store::find($order->store_id);
                if ($store) {
                    $order->tenant_id = $store->tenant_id;
                }
            }
        });

        // Trigger COGS processing when order status changes to completed
        static::saved(function (Order $order) {
            if ($order->wasChanged('status') && $order->status === 'completed') {
                // Check if COGS already processed (idempotency)
                if (!\App\Models\CogsHistory::where('order_id', $order->id)->exists()) {
                    \App\Jobs\ProcessOrderCogsJob::dispatch($order->id);
                }
            }
        });
    }

    /**
     * Get the tenant that owns the order.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
     * Get the order-level discounts applied to this order.
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    /**
     * Get the refunds for the order.
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Check if order has pending payment.
     */
    public function hasPendingPayment(): bool
    {
        return $this->payments()->where('status', 'pending')->exists();
    }

    /**
     * Get pending payment for this order.
     */
    public function getPendingPayment(): ?Payment
    {
        return $this->payments()->where('status', 'pending')->first();
    }

    /**
     * Get total paid amount (accessor).
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->getTotalPaid();
    }

    /**
     * Get remaining balance (accessor).
     */
    public function getRemainingBalanceAttribute(): float
    {
        return $this->getRemainingBalance();
    }

    /**
     * Generate unique order number with retry mechanism to prevent race conditions.
     * 
     * @param int $maxRetries Maximum number of retry attempts
     * @return string Unique order number in format ORD{YYYYMMDD}{0001-9999}
     */
    public static function generateOrderNumber(int $maxRetries = 10): string
    {
        $prefix = 'ORD';
        $date = now()->format('Ymd');
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            // Use database transaction with lock to prevent race condition
            $orderNumber = DB::transaction(function () use ($prefix, $date) {
                // Lock the table/rows to prevent concurrent access
                // Get the maximum sequence number for today
                $maxOrder = static::lockForUpdate()
                    ->whereDate('created_at', now())
                    ->where('order_number', 'like', $prefix . $date . '%')
                    ->orderByRaw('CAST(SUBSTRING(order_number, -4) AS UNSIGNED) DESC')
                    ->first();
                
                $maxSequence = 0;
                if ($maxOrder && $maxOrder->order_number) {
                    $orderNum = $maxOrder->order_number;
                    if (str_starts_with($orderNum, $prefix . $date)) {
                        $seqPart = substr($orderNum, -4);
                        $maxSequence = (int) $seqPart;
                    }
                }
                
                // Generate next sequence number
                $sequence = $maxSequence + 1;
                $orderNumber = $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
                
                // Verify this order number doesn't exist (double-check)
                if (!static::where('order_number', $orderNumber)->exists()) {
                    return $orderNumber;
                }
                
                // If exists, increment and try again
                $sequence++;
                return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            });
            
            // Final check: verify the generated order number doesn't exist
            if (!static::where('order_number', $orderNumber)->exists()) {
                return $orderNumber;
            }
            
            // If still exists, wait a bit and retry
            usleep(rand(10000, 50000)); // Random delay 10-50ms to reduce collision
        }
        
        // Fallback: use timestamp-based unique number if all retries fail
        // This should rarely happen, but provides a safety net
        $timestamp = now()->format('His'); // Hours, minutes, seconds
        $microseconds = substr((string) microtime(true), -3); // Last 3 digits of microseconds
        return $prefix . $date . substr($timestamp . $microseconds, -4);
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

        // Note: Member stats update is handled by LoyaltyService via OrderObserver
        // to avoid duplicate updates and maintain single source of truth

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
        return $this->refunds()->where('status', 'processed')->sum('amount');
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
