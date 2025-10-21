<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class CogsHistory extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $table = 'cogs_history';

    protected $fillable = [
        'store_id',
        'product_id',
        'order_id',
        'quantity_sold',
        'unit_cost',
        'total_cogs',
        'calculation_method',
        'cost_breakdown',
    ];

    protected $casts = [
        'quantity_sold' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'cost_breakdown' => 'array',
    ];

    // Calculation methods
    const METHOD_FIFO = 'fifo';
    const METHOD_LIFO = 'lifo';
    const METHOD_WEIGHTED_AVERAGE = 'weighted_average';

    /**
     * Get the product associated with this COGS record.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the order associated with this COGS record.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Calculate COGS for a product sale.
     */
    public static function calculateCogs(
        string $productId,
        int $quantitySold,
        string $method = self::METHOD_WEIGHTED_AVERAGE,
        ?string $orderId = null
    ): self {
        $product = Product::findOrFail($productId);
        $stockLevel = StockLevel::getOrCreateForProduct($productId);
        
        switch ($method) {
            case self::METHOD_FIFO:
                return self::calculateFifoCogs($product, $quantitySold, $orderId);
            case self::METHOD_LIFO:
                return self::calculateLifoCogs($product, $quantitySold, $orderId);
            default:
                return self::calculateWeightedAverageCogs($product, $stockLevel, $quantitySold, $orderId);
        }
    }

    /**
     * Calculate COGS using weighted average method.
     */
    protected static function calculateWeightedAverageCogs(
        Product $product,
        StockLevel $stockLevel,
        int $quantitySold,
        ?string $orderId = null
    ): self {
        $unitCost = $stockLevel->average_cost;
        $totalCogs = $unitCost * $quantitySold;

        return self::create([
            'store_id' => self::resolveStoreId($product),
            'product_id' => $product->id,
            'order_id' => $orderId,
            'quantity_sold' => $quantitySold,
            'unit_cost' => $unitCost,
            'total_cogs' => $totalCogs,
            'calculation_method' => self::METHOD_WEIGHTED_AVERAGE,
        ]);
    }

    /**
     * Calculate COGS using FIFO method.
     */
    protected static function calculateFifoCogs(
        Product $product,
        int $quantitySold,
        ?string $orderId = null
    ): self {
        // Get stock movements in chronological order (oldest first)
        $stockInMovements = InventoryMovement::where('product_id', $product->id)
            ->stockIn()
            ->where('unit_cost', '>', 0)
            ->orderBy('created_at')
            ->get();

        $remainingQuantity = $quantitySold;
        $totalCogs = 0;
        $costBreakdown = [];

        foreach ($stockInMovements as $movement) {
            if ($remainingQuantity <= 0) break;

            $quantityFromThisBatch = min($remainingQuantity, $movement->quantity);
            $costFromThisBatch = $quantityFromThisBatch * $movement->unit_cost;
            
            $totalCogs += $costFromThisBatch;
            $remainingQuantity -= $quantityFromThisBatch;
            
            $costBreakdown[] = [
                'movement_id' => $movement->id,
                'quantity' => $quantityFromThisBatch,
                'unit_cost' => $movement->unit_cost,
                'total_cost' => $costFromThisBatch,
                'movement_date' => $movement->created_at,
            ];
        }

        $averageUnitCost = $quantitySold > 0 ? $totalCogs / $quantitySold : 0;

        return self::create([
            'store_id' => self::resolveStoreId($product),
            'product_id' => $product->id,
            'order_id' => $orderId,
            'quantity_sold' => $quantitySold,
            'unit_cost' => $averageUnitCost,
            'total_cogs' => $totalCogs,
            'calculation_method' => self::METHOD_FIFO,
            'cost_breakdown' => $costBreakdown,
        ]);
    }

    /**
     * Calculate COGS using LIFO method.
     */
    protected static function calculateLifoCogs(
        Product $product,
        int $quantitySold,
        ?string $orderId = null
    ): self {
        // Get stock movements in reverse chronological order (newest first)
        $stockInMovements = InventoryMovement::where('product_id', $product->id)
            ->stockIn()
            ->where('unit_cost', '>', 0)
            ->orderByDesc('created_at')
            ->get();

        $remainingQuantity = $quantitySold;
        $totalCogs = 0;
        $costBreakdown = [];

        foreach ($stockInMovements as $movement) {
            if ($remainingQuantity <= 0) break;

            $quantityFromThisBatch = min($remainingQuantity, $movement->quantity);
            $costFromThisBatch = $quantityFromThisBatch * $movement->unit_cost;
            
            $totalCogs += $costFromThisBatch;
            $remainingQuantity -= $quantityFromThisBatch;
            
            $costBreakdown[] = [
                'movement_id' => $movement->id,
                'quantity' => $quantityFromThisBatch,
                'unit_cost' => $movement->unit_cost,
                'total_cost' => $costFromThisBatch,
                'movement_date' => $movement->created_at,
            ];
        }

        $averageUnitCost = $quantitySold > 0 ? $totalCogs / $quantitySold : 0;

        return self::create([
            'store_id' => self::resolveStoreId($product),
            'product_id' => $product->id,
            'order_id' => $orderId,
            'quantity_sold' => $quantitySold,
            'unit_cost' => $averageUnitCost,
            'total_cogs' => $totalCogs,
            'calculation_method' => self::METHOD_LIFO,
            'cost_breakdown' => $costBreakdown,
        ]);
    }

    protected static function resolveStoreId(Product $product): ?string
    {
        return $product->store_id ?? auth()->user()?->currentStoreId();
    }
}
