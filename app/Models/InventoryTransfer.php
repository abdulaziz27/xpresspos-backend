<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransfer extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'from_store_id',
        'to_store_id',
        'transfer_number',
        'status',
        'shipped_at',
        'received_at',
        'notes',
    ];

    /**
     * The "booted" method of the model.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->tenant_id && $model->from_store_id) {
                $store = Store::find($model->from_store_id);
                if ($store) {
                    $model->tenant_id = $store->tenant_id;
                }
            }

            // Auto-generate transfer_number if not set
            if (!$model->transfer_number) {
                $model->transfer_number = static::generateTransferNumber();
            }
        });
    }

    /**
     * Generate transfer number in format: TRANS-YYYYMMDD-XXX
     */
    public static function generateTransferNumber(): string
    {
        $date = now()->format('Ymd');
        $lastTransfer = static::whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->first();

        if ($lastTransfer && preg_match('/TRANS-' . $date . '-(\d+)/', $lastTransfer->transfer_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('TRANS-%s-%03d', $date, $sequence);
    }

    /**
     * Get total number of items in this transfer.
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get total quantity shipped (sum of quantity_shipped).
     */
    public function getTotalQtyAttribute(): float
    {
        return (float) $this->items()->sum('quantity_shipped');
    }

    /**
     * TODO: Generate inventory_movements when status changes to 'shipped' or 'received'.
     * 
     * This method should:
     * 1. When status = 'shipped':
     *    - Create InventoryMovement (type 'transfer_out') for from_store
     *    - Update StockLevel for from_store
     * 2. When status = 'received':
     *    - Create InventoryMovement (type 'transfer_in') for to_store
     *    - Update StockLevel for to_store
     * 3. Ensure idempotency (don't create duplicate movements)
     * 
     * This will be implemented in a future wave.
     */
    public function generateInventoryMovements(): void
    {
        // TODO: Implement in future wave
        // This method will create inventory_movements based on transfer items
        // when the transfer is shipped/received.
    }

    /**
     * Get the tenant that owns the inventory transfer.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class);
    }
}

