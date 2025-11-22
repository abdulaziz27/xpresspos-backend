<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToStore;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'supplier_id',
        'po_number',
        'status',
        'ordered_at',
        'received_at',
        'total_amount',
        'notes',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->tenant_id && $model->store_id) {
                $store = Store::find($model->store_id);
                if ($store) {
                    $model->tenant_id = $store->tenant_id;
                }
            }

            // Auto-generate po_number if not set
            if (!$model->po_number) {
                $model->po_number = static::generatePoNumber($model->store_id);
            }
        });

        // Auto-calculate total_amount from items before saving
        static::saving(function ($model) {
            if ($model->isDirty() || !$model->exists) {
                $model->recalculateTotalAmount();
            }
        });
    }

    /**
     * Generate PO number in format: PO-YYYYMMDD-XXX
     */
    public static function generatePoNumber(?string $storeId = null): string
    {
        $date = now()->format('Ymd');
        $query = static::query();
        
        if ($storeId) {
            $query->where('store_id', $storeId);
        }
        
        // Find last PO with same date pattern
        $lastPo = $query->where('po_number', 'like', 'PO-' . $date . '-%')
            ->orderByDesc('po_number')
            ->first();

        if ($lastPo && preg_match('/PO-' . $date . '-(\d+)/', $lastPo->po_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('PO-%s-%03d', $date, $sequence);
    }

    /**
     * Recalculate total_amount from items.
     */
    public function recalculateTotalAmount(): void
    {
        $total = $this->items()->sum('total_cost');
        $this->total_amount = $total;
        // Don't save here - let the saving event or saveQuietly handle it
    }

    /**
     * TODO: Generate inventory_lots and inventory_movements when status changes to 'received'.
     * 
     * This method should:
     * 1. For each purchase_order_item:
     *    - Create InventoryLot (if track_lot = true)
     *    - Create InventoryMovement (type 'purchase')
     *    - Update StockLevel
     * 2. Only process if status is 'received'
     * 3. Ensure idempotency (don't create duplicate lots/movements)
     * 
     * This will be implemented in a future wave.
     */
    public function generateInventoryLotsAndMovements(): void
    {
        // TODO: Implement in future wave
        // This method will create inventory_lots and inventory_movements
        // when the PO is received.
    }

    /**
     * Get the tenant that owns the purchase order.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}

