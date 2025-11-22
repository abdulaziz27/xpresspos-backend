<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Scopes\TenantScope;

class Product extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        
        // Automatically set tenant_id when creating
        static::creating(function ($model) {
            if (!$model->tenant_id && auth()->check()) {
                $user = auth()->user();
                $tenantId = $user->currentTenant()?->id;
                
                if (!$tenantId) {
                    throw new \Exception('Tidak dapat menentukan tenant aktif untuk pengguna.');
                }
                
                $model->tenant_id = $tenantId;
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'sku',
        'description',
        'image',
        'price',
        'cost_price',
        'track_inventory',
        // 'variants', // Removed - use product_options relationship instead
        'status',
        'is_favorite',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'track_inventory' => 'boolean',
        'status' => 'boolean',
        'is_favorite' => 'boolean',
        // 'variants' => 'array', // Removed - use product_options relationship instead
    ];



    /**
     * Get the tenant that owns the product.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the variants for the product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Alias for backwards compatibility
     */
    public function options(): HasMany
    {
        return $this->variants();
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the price history for the product.
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    /**
     * Get the inventory movements for the product.
     * 
     * @deprecated Product-based inventory relations are deprecated. Stock is now tracked per inventory_item, not per product.
     * Use InventoryItem::inventoryMovements() instead.
     */
    public function inventoryMovements(): HasMany
    {
        throw new \Exception(
            'Product::inventoryMovements() is deprecated. ' .
            'Product-based inventory relations are deprecated due to inventory refactor. ' .
            'Use InventoryItem::inventoryMovements() instead.'
        );
    }

    /**
     * Get the stock level for the product.
     * 
     * @deprecated Product-based inventory relations are deprecated. Stock is now tracked per inventory_item, not per product.
     * Use InventoryItem::stockLevels() instead.
     */
    public function stockLevel()
    {
        throw new \Exception(
            'Product::stockLevel() is deprecated. ' .
            'Product-based inventory relations are deprecated due to inventory refactor. ' .
            'Use InventoryItem::stockLevels() instead.'
        );
    }

    /**
     * Get the COGS history for the product.
     */
    public function cogsHistory(): HasMany
    {
        return $this->hasMany(CogsHistory::class);
    }

    /**
     * Get the recipes for the product.
     */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /**
     * Get the active recipe for the product.
     * Returns the first active recipe, or null if none exists.
     */
    public function getActiveRecipe(): ?Recipe
    {
        return $this->recipes()
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Recalculate cost_price from active recipe.
     * Sets cost_price to the cost_per_unit of the active recipe.
     */
    public function recalculateCostPriceFromRecipe(): void
    {
        $activeRecipe = $this->getActiveRecipe();
        
        if ($activeRecipe && $activeRecipe->cost_per_unit > 0) {
            $this->cost_price = $activeRecipe->cost_per_unit;
            $this->save();
        }
    }

    /**
     * Get modifier groups attached to the product.
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups')
            ->withPivot(['is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Check if product is low on stock.
     * Note: Stock is now tracked via stock_levels table, not product.stock column
     */
    public function isLowStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }
        
        $stockLevel = $this->stockLevel;
        if (!$stockLevel) {
            return false;
        }
        
        return $stockLevel->current_stock <= $stockLevel->min_stock_level;
    }

    /**
     * Check if product is out of stock.
     * Note: Stock is now tracked via stock_levels table, not product.stock column
     */
    public function isOutOfStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
    }

        $stockLevel = $this->stockLevel;
        if (!$stockLevel) {
            return true;
        }
        
        return $stockLevel->current_stock <= 0;
    }

    /**
     * Scope to get active products.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get favorite products.
     */
    public function scopeFavorite($query)
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Scope to get low stock products.
     * Note: Stock is now tracked via stock_levels table
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_inventory', true)
            ->whereHas('stockLevel', function ($q) {
                $q->whereColumn('current_stock', '<=', 'min_stock_level');
            });
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Record price change in history.
     */
    public function recordPriceChange(
        float $newPrice,
        ?float $newCostPrice = null,
        ?string $reason = null
    ): void {
        if ($this->price != $newPrice || ($newCostPrice !== null && $this->cost_price != $newCostPrice)) {
            $user = auth()->user() ?? request()->user();
            $this->priceHistory()->create([
                'tenant_id' => $this->tenant_id,
                'old_price' => $this->price,
                'new_price' => $newPrice,
                'old_cost_price' => $this->cost_price,
                'new_cost_price' => $newCostPrice,
                'changed_by' => $user?->id,
                'reason' => $reason,
                'effective_date' => now(),
            ]);
        }
    }

    /**
     * Update price with history tracking.
     */
    public function updatePrice(float $newPrice, ?float $newCostPrice = null, ?string $reason = null): void
    {
        $this->recordPriceChange($newPrice, $newCostPrice, $reason);

        $this->update([
            'price' => $newPrice,
            'cost_price' => $newCostPrice ?? $this->cost_price,
        ]);
    }

    /**
     * Archive the product (soft delete alternative).
     */
    public function archive(): void
    {
        $this->update(['status' => false]);
    }

    /**
     * Restore the archived product.
     */
    public function restore(): void
    {
        $this->update(['status' => true]);
    }

    /**
     * Calculate total price with selected options.
     */
    public function calculatePriceWithOptions(array $optionIds = []): array
    {
        $basePrice = $this->price;
        $totalAdjustment = 0;
        $selectedOptions = [];

        if (!empty($optionIds)) {
            $options = ProductVariant::withoutGlobalScopes()
                ->whereIn('id', $optionIds)
                ->where('product_id', $this->id)
                ->where('is_active', true)
                ->get();

            foreach ($options as $option) {
                $totalAdjustment += $option->price_adjustment;
                $selectedOptions[] = [
                    'id' => $option->id,
                    'name' => $option->name,
                    'value' => $option->value,
                    'price_adjustment' => $option->price_adjustment,
                ];
            }
        }

        return [
            'base_price' => $basePrice,
            'total_adjustment' => $totalAdjustment,
            'total_price' => $basePrice + $totalAdjustment,
            'selected_options' => $selectedOptions,
        ];
    }

    /**
     * Get available option groups for this product.
     */
    public function getOptionGroups(): array
    {
        $options = ProductVariant::withoutGlobalScopes()
            ->where('product_id', $this->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('name');

        return $options->map(function ($groupOptions, $groupName) {
            return [
                'name' => $groupName,
                'options' => $groupOptions->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'value' => $option->value,
                        'price_adjustment' => $option->price_adjustment,
                        'sort_order' => $option->sort_order,
                    ];
                })->values(),
            ];
        })->values()->toArray();
    }

    /**
     * Validate selected options for this product.
     */
    public function validateOptions(array $optionIds): array
    {
        $errors = [];

        if (empty($optionIds)) {
            return $errors;
        }

        // Get all valid options for this product
        $validOptions = ProductVariant::withoutGlobalScopes()
            ->whereIn('id', $optionIds)
            ->where('product_id', $this->id)
            ->where('is_active', true)
            ->get();

        // Check if all provided option IDs are valid
        $validOptionIds = $validOptions->pluck('id')->toArray();
        $invalidIds = array_diff($optionIds, $validOptionIds);

        if (!empty($invalidIds)) {
            $errors[] = 'Invalid option IDs: ' . implode(', ', $invalidIds);
        }

        // Check for duplicate option groups (can't select multiple values from same group)
        $optionGroups = $validOptions->groupBy('name');
        foreach ($optionGroups as $groupName => $groupOptions) {
            if ($groupOptions->count() > 1) {
                $errors[] = "Cannot select multiple values from option group: {$groupName}";
            }
        }

        return $errors;
    }
}
