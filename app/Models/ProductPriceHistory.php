<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Scopes\TenantScope;

class ProductPriceHistory extends Model
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
        'old_price',
        'new_price',
        'old_cost_price',
        'new_cost_price',
        'changed_by',
        'reason',
        'effective_date',
    ];

    protected $casts = [
        'old_price' => 'decimal:2',
        'new_price' => 'decimal:2',
        'old_cost_price' => 'decimal:2',
        'new_cost_price' => 'decimal:2',
        'effective_date' => 'datetime',
    ];

    /**
     * Get the tenant that owns the price history.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the product that owns the price history.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who made the price change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}