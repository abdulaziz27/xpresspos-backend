<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class CogsHistory extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $table = 'cogs_history';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'product_id',
        'order_id',
        'quantity_sold',
        'unit_cost',
        'total_cogs',
        'calculation_method',
        'cost_breakdown',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
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
     * Get the tenant that owns the COGS history.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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
     * Get the COGS details for this history record.
     */
    public function cogsDetails(): HasMany
    {
        return $this->hasMany(CogsDetail::class);
    }

    /**
     * Calculate COGS for a product sale.
     * 
     * NOTE: COGS is still per product, but stock calculations now use inventory_item.
     * For recipe-based products, COGS is calculated from recipe ingredients (inventory_items).
     * For non-recipe products, this method is deprecated and will throw exception.
     * 
     * @deprecated For non-recipe products, use recipe-based COGS calculation instead.
     * This method will be redesigned in Wave 3 for full inventory-item-based COGS.
     */
    public static function calculateCogs(
        string $productId,
        int $quantitySold,
        string $method = self::METHOD_WEIGHTED_AVERAGE,
        ?string $orderId = null
    ): self {
        $product = Product::findOrFail($productId);
        
        // Check if product has a recipe - if yes, COGS should be calculated from recipe
        // This method is deprecated for direct product COGS calculation
        throw new \Exception(
            'CogsHistory::calculateCogs() is deprecated for direct product COGS. ' .
            'COGS per product via stock_levels/product_id is deprecated due to inventory refactor. ' .
            'Use recipe-based COGS calculations (via CogsService) or redesign for inventory-item-based COGS in Wave 3.'
        );
    }

    /**
     * Calculate COGS using weighted average method.
     * 
     * @deprecated This method uses StockLevel with product_id which is no longer valid.
     * For recipe-based products, COGS should be calculated from recipe ingredients.
     * Will be redesigned in Wave 3 for inventory-item-based COGS.
     */
    protected static function calculateWeightedAverageCogs(
        Product $product,
        StockLevel $stockLevel,
        int $quantitySold,
        ?string $orderId = null
    ): self {
        throw new \Exception(
            'calculateWeightedAverageCogs() is deprecated. ' .
            'COGS per product via stock_levels/product_id is deprecated due to inventory refactor. ' .
            'Use recipe-based COGS calculations (via CogsService) or redesign for inventory-item-based COGS in Wave 3.'
        );
    }

    /**
     * Calculate COGS using FIFO method.
     * 
     * @deprecated This method uses product_id for inventory_movements which is no longer valid.
     * Will be redesigned in Wave 3 for inventory-item-based COGS.
     */
    protected static function calculateFifoCogs(
        Product $product,
        int $quantitySold,
        ?string $orderId = null
    ): self {
        throw new \Exception(
            'calculateFifoCogs() is deprecated. ' .
            'COGS per product via inventory_movements/product_id is deprecated due to inventory refactor. ' .
            'Use recipe-based COGS calculations or redesign for inventory-item-based COGS in Wave 3.'
        );
    }

    /**
     * Calculate COGS using LIFO method.
     * 
     * @deprecated This method uses product_id for inventory_movements which is no longer valid.
     * Will be redesigned in Wave 3 for inventory-item-based COGS.
     */
    protected static function calculateLifoCogs(
        Product $product,
        int $quantitySold,
        ?string $orderId = null
    ): self {
        throw new \Exception(
            'calculateLifoCogs() is deprecated. ' .
            'COGS per product via inventory_movements/product_id is deprecated due to inventory refactor. ' .
            'Use recipe-based COGS calculations or redesign for inventory-item-based COGS in Wave 3.'
        );
    }

    protected static function resolveStoreId(Product $product): ?string
    {
        $storeContext = \App\Services\StoreContext::instance();
        $user = auth()->user();
        return $storeContext->current($user);
    }
}
