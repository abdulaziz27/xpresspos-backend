<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class InventoryMovement extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'product_id',
        'user_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reason',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Movement types
    const TYPE_SALE = 'sale';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_RETURN = 'return';
    const TYPE_WASTE = 'waste';

    /**
     * Get the product associated with the movement.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who recorded the movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reference model (polymorphic).
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Scope to get movements by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get stock in movements.
     */
    public function scopeStockIn($query)
    {
        return $query->whereIn('type', [
            self::TYPE_PURCHASE,
            self::TYPE_ADJUSTMENT_IN,
            self::TYPE_TRANSFER_IN,
            self::TYPE_RETURN
        ]);
    }

    /**
     * Scope to get stock out movements.
     */
    public function scopeStockOut($query)
    {
        return $query->whereIn('type', [
            self::TYPE_SALE,
            self::TYPE_ADJUSTMENT_OUT,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_WASTE
        ]);
    }

    /**
     * Check if this is a stock increase movement.
     */
    public function isStockIncrease(): bool
    {
        return in_array($this->type, [
            self::TYPE_PURCHASE,
            self::TYPE_ADJUSTMENT_IN,
            self::TYPE_TRANSFER_IN,
            self::TYPE_RETURN
        ]);
    }

    /**
     * Check if this is a stock decrease movement.
     */
    public function isStockDecrease(): bool
    {
        return in_array($this->type, [
            self::TYPE_SALE,
            self::TYPE_ADJUSTMENT_OUT,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_WASTE
        ]);
    }

    /**
     * Get the signed quantity (positive for stock in, negative for stock out).
     */
    public function getSignedQuantity(): int
    {
        return $this->isStockIncrease() ? $this->quantity : -$this->quantity;
    }

    /**
     * Create a stock movement record.
     */
    public static function createMovement(
        string $productId,
        string $type,
        int $quantity,
        ?float $unitCost = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): self {
        return self::create([
            'store_id' => auth()->user()->store_id,
            'product_id' => $productId,
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity' => abs($quantity), // Always store positive quantity
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost ? $unitCost * abs($quantity) : null,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
        ]);
    }
}
