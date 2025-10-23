<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\StoreScope;

class ProductVariant extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'product_variants';
    
    protected $fillable = [
        'store_id',
        'product_id',
        'name',
        'value',
        'price_adjustment',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new StoreScope);
    }

    /**
     * Get the product that owns the option.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store that owns the product option.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope to get active options.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Check if this option affects inventory tracking.
     * For now, we'll track inventory at the product level,
     * but this can be extended for variant-specific inventory.
     */
    public function affectsInventory(): bool
    {
        $product = $this->product ?: Product::withoutGlobalScopes()->find($this->product_id);
        return $product && $product->track_inventory;
    }

    /**
     * Get the effective price for this option.
     */
    public function getEffectivePrice(): float
    {
        $product = $this->product ?: Product::withoutGlobalScopes()->find($this->product_id);
        return $product->price + $this->price_adjustment;
    }

    /**
     * Check if this option is available (active and product is active).
     */
    public function isAvailable(): bool
    {
        $product = $this->product ?: Product::withoutGlobalScopes()->find($this->product_id);
        return $this->is_active && 
               $product && 
               $product->status && 
               (!$product->track_inventory || $product->stock > 0);
    }

    /**
     * Get formatted option display name.
     */
    public function getDisplayName(): string
    {
        return "{$this->name}: {$this->value}";
    }

    /**
     * Get formatted price adjustment display.
     */
    public function getPriceAdjustmentDisplay(): string
    {
        if ($this->price_adjustment == 0) {
            return '';
        }
        
        $sign = $this->price_adjustment > 0 ? '+' : '';
        return $sign . number_format($this->price_adjustment, 0, ',', '.');
    }
}
