<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;
use App\Models\Scopes\TenantScope;

class InventoryMovement extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'inventory_item_id',
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

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        
        static::creating(function ($model) {
            if (!$model->tenant_id && $model->store_id) {
                $store = Store::find($model->store_id);
                if ($store) {
                    $model->tenant_id = $store->tenant_id;
                }
            }
        });
    }

    /**
     * Get the tenant that owns the inventory movement.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'quantity' => 'decimal:3',
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
     * Get the inventory item associated with the movement.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @deprecated Use inventoryItem() instead. Stock is now tracked per inventory_item, not per product.
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
    public function getSignedQuantity(): float
    {
        return $this->isStockIncrease() ? (float) $this->quantity : -(float) $this->quantity;
    }

    /**
     * Create a stock movement record.
     * 
     * @param string $inventoryItemId The inventory item ID (not product ID)
     * @param string $type Movement type
     * @param float $quantity Quantity (decimal, e.g., 1.5 kg)
     * @param float|null $unitCost Optional unit cost
     * @param string|null $reason Optional reason
     * @param string|null $referenceType Optional reference type (polymorphic)
     * @param string|null $referenceId Optional reference ID
     * @param string|null $notes Optional notes
     * @return self
     */
    public static function createMovement(
        string $inventoryItemId,
        string $type,
        float $quantity,
        ?float $unitCost = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): self {
        $user = auth()->user() ?? request()->user();
        $storeContext = \App\Services\StoreContext::instance();
        $storeId = $storeContext->current($user);

        if (!$storeId) {
            throw new \Exception('Store context is required to create inventory movement');
        }

        $store = Store::find($storeId);
        if (!$store) {
            throw new \Exception('Store not found');
        }

        return self::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $storeId,
            'inventory_item_id' => $inventoryItemId,
            'user_id' => $user?->id,
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
