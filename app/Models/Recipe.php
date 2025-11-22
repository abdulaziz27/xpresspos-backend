<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\TenantScope;

class Recipe extends Model
{
    use HasFactory, HasUuids;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
        
        // Automatically set tenant_id when creating
        static::creating(function ($model) {
            if (!$model->tenant_id && $model->product_id) {
                $product = Product::withoutTenantScope()->find($model->product_id);
                if ($product) {
                    $model->tenant_id = $product->tenant_id;
                }
            }
        });
    }

    protected $fillable = [
        'tenant_id',
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
     * Get the tenant that owns the recipe.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

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
        // Handle division by zero: treat yield_quantity 0 as 1
        $yieldQty = $this->yield_quantity > 0 ? $this->yield_quantity : 1;
        $unitCost = $totalCost / $yieldQty;
        $this->update(['cost_per_unit' => $unitCost]);
        return $unitCost;
    }

    /**
     * Recalculate all costs when recipe items change.
     * This is called automatically when recipe_items are saved/deleted.
     */
    public function recalculateCosts(): void
    {
        $this->calculateTotalCost();
        $this->calculateUnitCost();
        
        // If this recipe is active and belongs to a product, update product cost_price
        if ($this->is_active && $this->product_id) {
            // Load product relationship if not already loaded
            if (!$this->relationLoaded('product')) {
                $this->load('product');
            }
            
            if ($this->product) {
            $this->product->recalculateCostPriceFromRecipe();
            }
        }
    }

    /**
     * Scope to get active recipes.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
