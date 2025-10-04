<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class RecipeItem extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'recipe_id',
        'ingredient_product_id',
        'quantity',
        'unit',
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
     * Get the recipe that owns the recipe item.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the ingredient product.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function ($recipeItem) {
            $recipeItem->total_cost = $recipeItem->quantity * $recipeItem->unit_cost;
        });

        static::saved(function ($recipeItem) {
            // Recalculate recipe costs when item is saved
            $recipeItem->recipe->recalculateCosts();
        });

        static::deleted(function ($recipeItem) {
            // Recalculate recipe costs when item is deleted
            $recipeItem->recipe->recalculateCosts();
        });
    }

    /**
     * Calculate total cost for this ingredient.
     */
    public function getTotalCost(): float
    {
        return $this->quantity * $this->unit_cost;
    }
}
