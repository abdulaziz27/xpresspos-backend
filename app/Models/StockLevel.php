<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class StockLevel extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'product_id',
        'current_stock',
        'reserved_stock',
        'available_stock',
        'average_cost',
        'total_value',
        'last_movement_at',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'reserved_stock' => 'integer',
        'available_stock' => 'integer',
        'average_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
        'last_movement_at' => 'datetime',
    ];

    /**
     * Get the product associated with this stock level.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Update stock level after a movement.
     */
    public function updateFromMovement(InventoryMovement $movement): void
    {
        $signedQuantity = $movement->getSignedQuantity();

        // Recalculate average cost for stock increases with cost BEFORE updating stock
        if ($movement->isStockIncrease() && $movement->unit_cost) {
            $this->recalculateAverageCost($movement);
        }

        // Update current stock
        $this->current_stock += $signedQuantity;

        // Update available stock (current - reserved)
        $this->available_stock = $this->current_stock - $this->reserved_stock;

        // Update total value
        $this->total_value = $this->current_stock * $this->average_cost;

        // Update last movement timestamp
        $this->last_movement_at = $movement->created_at;

        $this->save();
    }

    /**
     * Recalculate weighted average cost.
     */
    protected function recalculateAverageCost(InventoryMovement $movement): void
    {
        // Only recalculate if we have a unit cost
        if (!$movement->unit_cost) {
            return;
        }

        $currentValue = $this->current_stock * $this->average_cost;
        $newValue = $movement->quantity * $movement->unit_cost;
        $totalQuantity = $this->current_stock + $movement->quantity;

        if ($totalQuantity > 0) {
            $this->average_cost = ($currentValue + $newValue) / $totalQuantity;
        }
    }

    /**
     * Reserve stock for an order.
     */
    public function reserveStock(int $quantity): bool
    {
        if ($this->available_stock >= $quantity) {
            $this->reserved_stock += $quantity;
            $this->available_stock -= $quantity;
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Release reserved stock.
     */
    public function releaseReservedStock(int $quantity): void
    {
        $releaseQuantity = min($quantity, $this->reserved_stock);
        $this->reserved_stock -= $releaseQuantity;
        $this->available_stock += $releaseQuantity;
        $this->save();
    }

    /**
     * Check if product is low on stock.
     */
    public function isLowStock(): bool
    {
        return $this->product &&
            $this->product->track_inventory &&
            $this->current_stock <= $this->product->min_stock_level;
    }

    /**
     * Check if product is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->product &&
            $this->product->track_inventory &&
            $this->available_stock <= 0;
    }

    /**
     * Get or create stock level for a product.
     */
    public static function getOrCreateForProduct(string $productId, ?string $storeId = null): self
    {
        $user = auth()->user() ?? request()->user();
        $storeId = $storeId ?? ($user ? $user->store_id : null);

        if (!$storeId) {
            throw new \Exception('Store ID is required to get or create stock level');
        }

        return self::firstOrCreate(
            [
                'store_id' => $storeId,
                'product_id' => $productId,
            ],
            [
                'current_stock' => 0,
                'reserved_stock' => 0,
                'available_stock' => 0,
                'average_cost' => 0,
                'total_value' => 0,
            ]
        );
    }
}
