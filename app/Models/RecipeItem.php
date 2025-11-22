<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\TenantScope;

class RecipeItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'recipe_id',
        'inventory_item_id',
        'quantity',
        'uom_id',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    /**
     * Get the tenant that owns the recipe item.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the recipe that owns the recipe item.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the inventory item (ingredient).
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Get the UOM (unit of measurement) for this recipe item.
     * UOM is always the same as the inventory item's base UOM.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    /**
     * Alias for backwards compatibility (if needed).
     * @deprecated Use inventoryItem() instead
     */
    public function ingredient(): BelongsTo
    {
        return $this->inventoryItem();
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        
        // Automatically set tenant_id when creating
        static::creating(function ($model) {
            if (!$model->tenant_id && $model->recipe_id) {
                $recipe = Recipe::withoutTenantScope()->find($model->recipe_id);
                if ($recipe) {
                    $model->tenant_id = $recipe->tenant_id;
                }
            }
        });

        static::saving(function (RecipeItem $recipeItem) {
            // Enforce uom_id = inventoryItem->uom_id (base UOM)
            if ($recipeItem->inventory_item_id) {
                $inventoryItem = InventoryItem::find($recipeItem->inventory_item_id);
                if ($inventoryItem && $inventoryItem->uom_id) {
                    $recipeItem->uom_id = $inventoryItem->uom_id;
                }
            }

            // Set unit_cost from inventory_item.default_cost if not set
            if (!$recipeItem->unit_cost || $recipeItem->unit_cost == 0) {
                if ($recipeItem->inventory_item_id) {
                    $inventoryItem = InventoryItem::find($recipeItem->inventory_item_id);
                    if ($inventoryItem && $inventoryItem->default_cost) {
                        $recipeItem->unit_cost = $inventoryItem->default_cost;
                    }
                }
            }

            // Recalculate total_cost
            $recipeItem->recalculateCosts();
        });

        static::saved(function ($recipeItem) {
            $recipe = Recipe::find($recipeItem->recipe_id);
            $recipe?->recalculateCosts();
        });

        static::deleted(function ($recipeItem) {
            $recipe = Recipe::find($recipeItem->recipe_id);
            $recipe?->recalculateCosts();
        });
    }

    /**
     * Recalculate costs for this recipe item.
     * Calculates total_cost = quantity * unit_cost.
     * Note: unit_cost should already be set from inventory_item.default_cost in saving event.
     */
    public function recalculateCosts(): void
    {
        // Calculate total_cost = quantity * unit_cost
        $this->total_cost = ($this->quantity ?? 0) * ($this->unit_cost ?? 0);
    }

    /**
     * Calculate total cost for this ingredient.
     * @deprecated Use recalculateCosts() instead
     */
    public function getTotalCost(): float
    {
        return $this->quantity * $this->unit_cost;
    }
}
