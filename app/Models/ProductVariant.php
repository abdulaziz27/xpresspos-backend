<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\TenantScope;

class ProductVariant extends Model
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
                } elseif (auth()->check()) {
                    $user = auth()->user();
                    $tenantId = $user->currentTenant()?->id;
                    if ($tenantId) {
                        $model->tenant_id = $tenantId;
                    }
                }
            }
        });
    }

    protected $table = 'product_variants';
    
    protected $fillable = [
        'tenant_id',
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
     * Get the tenant that owns the product variant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the product that owns the option.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
        if (!$this->is_active || !$product || !$product->status) {
            return false;
        }
        
        if ($product->track_inventory) {
            $stockLevel = $product->stockLevel;
            return $stockLevel && $stockLevel->current_stock > 0;
        }
        
        return true;
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
