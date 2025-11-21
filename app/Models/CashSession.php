<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\PaymentMethodEnum;
use App\Models\Concerns\BelongsToStore;
use App\Models\Payment;

class CashSession extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'opening_balance',
        'closing_balance',
        'status',
        'opened_at',
        'closed_at',
        'notes',
        // Note: cash_sales, cash_expenses, expected_balance, variance are auto-calculated (read-only)
        // These fields are NOT in fillable to prevent manual input
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'cash_expenses' => 'decimal:2',
        'variance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * Fields that are auto-calculated and should be read-only
     */
    protected array $calculatedFields = [
        'cash_sales',
        'cash_expenses',
        'expected_balance',
        'variance',
    ];



    /**
     * Get the user who manages the cash session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the expenses for the cash session.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Boot the model.
     * Automatically calculate and store calculated fields when saving.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (CashSession $session) {
            // Reset calculated cache to force fresh calculation
            $session->_calculated_cash_sales = null;
            $session->_calculated_cash_expenses = null;
            $session->_calculated_expected_balance = null;
            $session->_calculated_variance = null;

            // Calculate and store auto-calculated fields for display in database
            // These values are saved for querying/filtering in tables, but accessors can use them as cache
            // Accessors will recalculate real-time when needed (especially for active/open sessions)
            $cashSales = $session->calculateCashSales();
            $cashExpenses = $session->calculateCashExpenses();
            $expectedBalance = $session->calculateExpectedBalance();
            
            $session->attributes['cash_sales'] = $cashSales;
            $session->attributes['cash_expenses'] = $cashExpenses;
            $session->attributes['expected_balance'] = $expectedBalance;

            // Calculate variance if closing_balance is set
            if ($session->attributes['closing_balance'] ?? null) {
                $variance = $session->calculateVariance();
                $session->attributes['variance'] = $variance;
            } else {
                $session->attributes['variance'] = 0;
            }
        });

        static::creating(function (CashSession $session) {
            // Set opened_at if not provided
            if (! $session->opened_at) {
                $session->opened_at = now();
            }

            // Set status to 'open' if not provided
            if (! $session->status) {
                $session->status = 'open';
            }
        });

        static::updating(function (CashSession $session) {
            // If status is being changed to 'closed', set closed_at if not set
            if ($session->isDirty('status') && $session->status === 'closed' && ! $session->closed_at) {
                $session->closed_at = now();
            }
        });
    }

    /**
     * Close the cash session.
     * @deprecated Use direct update with status='closed' instead, calculations are automatic
     */
    public function close(float $closingBalance): void
    {
        $this->update([
            'closing_balance' => $closingBalance,
            'status' => 'closed',
            'closed_at' => $this->closed_at ?? now(),
        ]);
        // Variance and expected_balance are automatically calculated in saving event
    }


    /**
     * Cache for calculated values to avoid repeated queries.
     */
    protected ?float $_calculated_cash_sales = null;
    protected ?float $_calculated_cash_expenses = null;
    protected ?float $_calculated_expected_balance = null;
    protected ?float $_calculated_variance = null;

    /**
     * Calculate cash sales amount for this session (private method).
     * Automatically calculated from payments with cash method within session period.
     * 
     * IMPORTANT: Tenant/Store scoping:
     * - Payment is filtered by store_id which matches CashSession's store_id
     * - Store belongs to tenant (via tenant_id), ensuring tenant isolation
     * - Additional safety: Join to stores table to verify tenant_id match (prevents cross-tenant data leakage)
     * - Payment method 'cash' uses PaymentMethodEnum::CASH->value for consistency
     */
    private function calculateCashSales(): float
    {
        if (! $this->store_id || ! $this->opened_at) {
            return 0;
        }

        $endDate = $this->closed_at ?? now();

        $query = Payment::query()
            ->join('stores', 'payments.store_id', '=', 'stores.id')
            ->where('payments.store_id', $this->store_id)
            ->where('payments.payment_method', PaymentMethodEnum::CASH->value)
            ->where('payments.status', 'completed')
            ->whereRaw(
                'COALESCE(payments.paid_at, payments.processed_at, payments.created_at) >= ?',
                [$this->opened_at]
            )
            ->whereRaw(
                'COALESCE(payments.paid_at, payments.processed_at, payments.created_at) <= ?',
                [$endDate]
            );

        // Extra safety: If store relationship is loaded, verify tenant_id match
        // This prevents edge cases where store_id might be manipulated
        if ($this->relationLoaded('store') && $this->store) {
            $query->where('stores.tenant_id', $this->store->tenant_id);
        }

        return (float) $query->sum('payments.amount') ?: 0;
    }

    /**
     * Get cash sales amount for this session (accessor).
     * Uses cached value if available, otherwise calculates real-time from source data.
     * For performance: uses database value as cache for closed sessions, recalculates for open sessions.
     */
    public function getCashSalesAttribute($value): float
    {
        // If already calculated in this request, return cached value
        if ($this->_calculated_cash_sales !== null) {
            return $this->_calculated_cash_sales;
        }

        // For closed sessions, use database value as cache (won't change)
        // For open sessions, always recalculate real-time (new payments may occur)
        if ($this->status === 'closed' && isset($this->attributes['cash_sales'])) {
            return $this->_calculated_cash_sales = (float) $this->attributes['cash_sales'];
        }

        // Recalculate for open sessions or if database value is not available
        return $this->_calculated_cash_sales = $this->calculateCashSales();
    }

    /**
     * Calculate cash expenses amount for this session (private method).
     * Automatically calculated from expenses related to this session.
     */
    private function calculateCashExpenses(): float
    {
        if (! $this->exists || ! $this->id) {
            return 0;
        }

        return (float) $this->expenses()->sum('amount') ?: 0;
    }

    /**
     * Get cash expenses amount for this session (accessor).
     * Uses cached value if available, otherwise calculates real-time from source data.
     * For performance: uses database value as cache for closed sessions, recalculates for open sessions.
     */
    public function getCashExpensesAttribute($value): float
    {
        // If already calculated in this request, return cached value
        if ($this->_calculated_cash_expenses !== null) {
            return $this->_calculated_cash_expenses;
        }

        // For closed sessions, use database value as cache (won't change)
        // For open sessions, always recalculate real-time (new expenses may be added)
        if ($this->status === 'closed' && isset($this->attributes['cash_expenses'])) {
            return $this->_calculated_cash_expenses = (float) $this->attributes['cash_expenses'];
        }

        // Recalculate for open sessions or if database value is not available
        return $this->_calculated_cash_expenses = $this->calculateCashExpenses();
    }

    /**
     * Calculate expected balance for this session (private method).
     * Formula: opening_balance + cash_sales - cash_expenses
     */
    private function calculateExpectedBalance(): float
    {
        $openingBalance = (float) ($this->attributes['opening_balance'] ?? 0);
        $cashSales = $this->calculateCashSales();
        $cashExpenses = $this->calculateCashExpenses();

        return $openingBalance + $cashSales - $cashExpenses;
    }

    /**
     * Get expected balance for this session (accessor).
     * Uses cached value if available, otherwise calculates real-time from source data.
     * For performance: uses database value as cache for closed sessions, recalculates for open sessions.
     */
    public function getExpectedBalanceAttribute($value): float
    {
        // If already calculated in this request, return cached value
        if ($this->_calculated_expected_balance !== null) {
            return $this->_calculated_expected_balance;
        }

        // For closed sessions, use database value as cache (won't change)
        // For open sessions, always recalculate real-time (sales/expenses may change)
        if ($this->status === 'closed' && isset($this->attributes['expected_balance'])) {
            return $this->_calculated_expected_balance = (float) $this->attributes['expected_balance'];
        }

        // Recalculate for open sessions or if database value is not available
        // Use cached calculated values to avoid redundant queries
        $openingBalance = (float) ($this->attributes['opening_balance'] ?? 0);
        $cashSales = $this->_calculated_cash_sales ?? $this->calculateCashSales();
        $cashExpenses = $this->_calculated_cash_expenses ?? $this->calculateCashExpenses();

        return $this->_calculated_expected_balance = $openingBalance + $cashSales - $cashExpenses;
    }

    /**
     * Calculate variance for this session (private method).
     * Formula: closing_balance - expected_balance (only if closing_balance is set)
     */
    private function calculateVariance(): float
    {
        $closingBalance = $this->attributes['closing_balance'] ?? null;
        if ($closingBalance === null) {
            return 0;
        }

        $expectedBalance = $this->calculateExpectedBalance();
        return (float) $closingBalance - $expectedBalance;
    }

    /**
     * Get variance for this session (accessor).
     * Uses cached value if available, otherwise calculates real-time from source data.
     * For performance: uses database value as cache for closed sessions, recalculates for open sessions.
     */
    public function getVarianceAttribute($value): float
    {
        // If already calculated in this request, return cached value
        if ($this->_calculated_variance !== null) {
            return $this->_calculated_variance;
        }

        // For closed sessions, use database value as cache (won't change)
        // For open sessions, always recalculate real-time
        if ($this->status === 'closed' && isset($this->attributes['variance'])) {
            return $this->_calculated_variance = (float) $this->attributes['variance'];
        }

        // Recalculate for open sessions or if database value is not available
        return $this->_calculated_variance = $this->calculateVariance();
    }

    /**
     * Get cash sales amount for this session (legacy method, kept for backwards compatibility).
     * @deprecated Use getCashSalesAttribute() instead
     */
    private function getCashSalesAmount(): float
    {
        return $this->getCashSalesAttribute();
    }

    /**
     * Check if session has variance.
     */
    public function hasVariance(): bool
    {
        return abs($this->variance) > 0.01; // Allow for small rounding differences
    }

    /**
     * Scope to get open sessions.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get closed sessions.
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }
}
