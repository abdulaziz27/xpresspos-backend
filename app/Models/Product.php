<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class Product extends Model
{
    use HasFactory, BelongsToStore;

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'sku',
        'description',
        'image',
        'price',
        'cost_price',
        'track_inventory',
        'stock',
        'min_stock_level',
        'variants',
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
        'variants' => 'array',
    ];



    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the options for the product.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
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
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get the stock level for the product.
     */
    public function stockLevel()
    {
        return $this->hasOne(StockLevel::class);
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
     * Check if product is low on stock.
     */
    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->stock <= $this->min_stock_level;
    }

    /**
     * Check if product is out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->track_inventory && $this->stock <= 0;
    }

    /**
     * Reduce stock quantity.
     */
    public function reduceStock(int $quantity): void
    {
        if ($this->track_inventory) {
            $this->decrement('stock', $quantity);
        }
    }

    /**
     * Increase stock quantity.
     */
    public function increaseStock(int $quantity): void
    {
        if ($this->track_inventory) {
            $this->increment('stock', $quantity);
        }
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
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_inventory', true)
            ->whereColumn('stock', '<=', 'min_stock_level');
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
                'store_id' => $this->store_id,
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
            $options = ProductOption::withoutGlobalScopes()
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
        $options = ProductOption::withoutGlobalScopes()
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
        $validOptions = ProductOption::withoutGlobalScopes()
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
