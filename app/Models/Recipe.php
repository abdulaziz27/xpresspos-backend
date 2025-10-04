<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToStore;

class Recipe extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'product_id',
        'name',
        'description',
        'yield_quantity',
        'yield_unit',
        'total_cost',
        'cost_per_unit',
        'is_active',
    ];

    protected $casts = [
        'yield_quantity' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
        'is_active' => 'boolean',
    ];



    /**
     * Get the product associated with the recipe.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the recipe items (ingredients).
     */
    public function items(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    /**
     * Calculate and update total cost of recipe.
     */
    public function calculateTotalCost(): float
    {
        $totalCost = $this->items()->sum('total_cost');
        $this->update(['total_cost' => $totalCost]);
        return $totalCost;
    }

    /**
     * Calculate and update cost per unit.
     */
    public function calculateUnitCost(): float
    {
        $totalCost = $this->total_cost ?: $this->calculateTotalCost();
        $unitCost = $this->yield_quantity > 0 ? $totalCost / $this->yield_quantity : 0;
        $this->update(['cost_per_unit' => $unitCost]);
        return $unitCost;
    }

    /**
     * Recalculate all costs when recipe items change.
     */
    public function recalculateCosts(): void
    {
        $this->calculateTotalCost();
        $this->calculateUnitCost();
    }

    /**
     * Scope to get active recipes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
