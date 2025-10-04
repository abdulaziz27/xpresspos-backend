<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class CashSession extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'cash_sales',
        'cash_expenses',
        'variance',
        'status',
        'opened_at',
        'closed_at',
        'notes',
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
     * Close the cash session.
     */
    public function close(float $closingBalance): void
    {
        $this->calculateExpectedBalance();
        
        $this->update([
            'closing_balance' => $closingBalance,
            'variance' => $closingBalance - $this->expected_balance,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Calculate expected balance based on sales and expenses.
     */
    public function calculateExpectedBalance(): void
    {
        $cashSales = $this->getCashSalesAmount();
        $cashExpenses = $this->expenses()->sum('amount');
        
        $expectedBalance = $this->opening_balance + $cashSales - $cashExpenses;
        
        $this->update([
            'cash_sales' => $cashSales,
            'cash_expenses' => $cashExpenses,
            'expected_balance' => $expectedBalance,
        ]);
    }

    /**
     * Get cash sales amount for this session.
     */
    private function getCashSalesAmount(): float
    {
        return Payment::where('store_id', $this->store_id)
            ->where('payment_method', 'cash')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$this->opened_at, $this->closed_at ?? now()])
            ->sum('amount');
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
