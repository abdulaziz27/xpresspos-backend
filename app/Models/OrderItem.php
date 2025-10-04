<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToStore;

class OrderItem extends Model
{
    use HasFactory, HasUuids, BelongsToStore;

    protected $fillable = [
        'store_id',
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'total_price',
        'product_options',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'product_options' => 'array',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function ($orderItem) {
            $orderItem->total_price = $orderItem->quantity * $orderItem->unit_price;
        });
    }

    /**
     * Get the order that owns the order item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product associated with the order item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate total price including options.
     */
    public function calculateTotalPrice(): float
    {
        $basePrice = $this->unit_price;
        $optionsPrice = 0;

        if ($this->product_options) {
            foreach ($this->product_options as $option) {
                $optionsPrice += $option['price_adjustment'] ?? 0;
            }
        }

        return ($basePrice + $optionsPrice) * $this->quantity;
    }

    /**
     * Update inventory when order item is created/updated.
     */
    public function updateInventory(): void
    {
        if ($this->product && $this->product->track_inventory) {
            $this->product->reduceStock($this->quantity);
        }
    }
}
