<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;
use App\Models\Scopes\TenantScope;

class StockLevel extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'inventory_item_id',
        'current_stock',
        'reserved_stock',
        'available_stock',
        'min_stock_level',
        'average_cost',
        'total_value',
        'last_movement_at',
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
     * Get the tenant that owns the stock level.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'current_stock' => 'decimal:3',
        'reserved_stock' => 'decimal:3',
        'available_stock' => 'decimal:3',
        'min_stock_level' => 'decimal:3',
        'average_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
        'last_movement_at' => 'datetime',
    ];

    /**
     * Get the inventory item associated with this stock level.
     * 
     * NOTE: Stock levels are maintained by the system (via service/logic),
     * not edited manually by users. These fields are denormalized aggregates.
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
    public function reserveStock(float $quantity): bool
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
    public function releaseReservedStock(float $quantity): void
    {
        $releaseQuantity = min($quantity, (float) $this->reserved_stock);
        $this->reserved_stock -= $releaseQuantity;
        $this->available_stock += $releaseQuantity;
        $this->save();
    }

    /**
     * Check if inventory item is low on stock.
     */
    public function isLowStock(): bool
    {
        $minLevel = $this->min_stock_level > 0 
            ? $this->min_stock_level 
            : ($this->inventoryItem?->min_stock_level ?? 0);
        
        return $this->inventoryItem &&
            $this->inventoryItem->track_stock &&
            $this->current_stock <= $minLevel;
    }

    /**
     * Check if inventory item is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->inventoryItem &&
            $this->inventoryItem->track_stock &&
            $this->available_stock <= 0;
    }

    /**
     * Get or create stock level for an inventory item.
     */
    public static function getOrCreateForInventoryItem(string $inventoryItemId, ?string $storeId = null): self
    {
        $user = auth()->user() ?? request()->user();
        $storeContext = \App\Services\StoreContext::instance();
        $storeId = $storeId ?? $storeContext->current($user);

        if (!$storeId) {
            throw new \Exception('Store ID is required to get or create stock level');
        }

        $store = Store::find($storeId);
        if (!$store) {
            throw new \Exception('Store not found');
        }

        $inventoryItem = InventoryItem::find($inventoryItemId);
        if (!$inventoryItem) {
            throw new \Exception('Inventory item not found');
        }

        return self::firstOrCreate(
            [
                'store_id' => $storeId,
                'inventory_item_id' => $inventoryItemId,
            ],
            [
                'tenant_id' => $store->tenant_id,
                'current_stock' => 0,
                'reserved_stock' => 0,
                'available_stock' => 0,
                'min_stock_level' => $inventoryItem->min_stock_level ?? 0,
                'average_cost' => 0,
                'total_value' => 0,
            ]
        );
    }

    /**
     * @deprecated Use getOrCreateForInventoryItem() instead. Stock is now tracked per inventory_item, not per product.
     */
    public static function getOrCreateForProduct(string $productId, ?string $storeId = null): self
    {
        throw new \Exception('getOrCreateForProduct() is deprecated. Use getOrCreateForInventoryItem() instead.');
    }
}
