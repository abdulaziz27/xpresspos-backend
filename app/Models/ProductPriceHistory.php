<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class ProductPriceHistory extends Model
{
    use HasFactory, BelongsToStore;

    protected $fillable = [
        'store_id',
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