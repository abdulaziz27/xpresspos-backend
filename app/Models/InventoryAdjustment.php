<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class InventoryAdjustment extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToStore;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_COUNT_DIFF = 'COUNT_DIFF';
    public const REASON_EXPIRED = 'EXPIRED';
    public const REASON_DAMAGE = 'DAMAGE';
    public const REASON_INITIAL = 'INITIAL';

    protected $fillable = [
        'tenant_id',
        'store_id',
        'user_id',
        'adjustment_number',
        'status',
        'reason',
        'adjusted_at',
        'notes',
    ];

    protected $casts = [
        'adjusted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if (! $adjustment->tenant_id && $adjustment->store_id) {
                $store = Store::find($adjustment->store_id);

                if ($store) {
                    $adjustment->tenant_id = $store->tenant_id;
                }
            }

            if (! $adjustment->user_id && auth()->check()) {
                $adjustment->user_id = auth()->id();
            }

            // Auto-generate adjustment_number if not set
            if (! $adjustment->adjustment_number) {
                $adjustment->adjustment_number = static::generateAdjustmentNumber();
            }
        });

        // Generate inventory movements when status changes to 'approved'
        static::saved(function (self $adjustment): void {
            if ($adjustment->isDirty('status') && $adjustment->status === self::STATUS_APPROVED) {
                $adjustment->generateInventoryMovements();
            }
        });
    }

    /**
     * Generate adjustment number in format: ADJ-YYYYMMDD-XXX
     */
    public static function generateAdjustmentNumber(): string
    {
        $date = now()->format('Ymd');
        $lastAdjustment = static::whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->first();

        if ($lastAdjustment && preg_match('/ADJ-' . $date . '-(\d+)/', $lastAdjustment->adjustment_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('ADJ-%s-%03d', $date, $sequence);
    }

    /**
     * Get total number of items in this adjustment.
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Get total value of adjustment (sum of abs(difference_qty * unit_cost)).
     */
    public function getTotalValueAttribute(): float
    {
        return (float) $this->items()->sum(DB::raw('ABS(difference_qty * unit_cost)'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }

    /**
     * Generate inventory_movements when status changes to 'approved'.
     * 
     * This method:
     * 1. For each adjustment item:
     *    - Create InventoryMovement with type 'adjustment_in' or 'adjustment_out' based on difference_qty
     *    - Update StockLevel accordingly via updateFromMovement()
     * 2. Only process if status is 'approved'
     * 3. Ensure idempotency (don't create duplicate movements)
     */
    public function generateInventoryMovements(): void
    {
        // Only generate movements if status is approved
        if ($this->status !== self::STATUS_APPROVED) {
            return;
        }

        // Check if movements already generated (idempotency)
        $existingMovement = InventoryMovement::where('reference_type', InventoryAdjustment::class)
            ->where('reference_id', $this->id)
            ->first();

        if ($existingMovement) {
            // Movements already generated, skip
            return;
        }

        if (!$this->store_id) {
            throw new \Exception('Store ID is required to generate inventory movements');
        }

        foreach ($this->items as $item) {
            if ($item->difference_qty == 0) {
                // No difference, skip
                continue;
            }

            // Determine movement type based on difference_qty
            $movementType = $item->difference_qty > 0 
                ? InventoryMovement::TYPE_ADJUSTMENT_IN 
                : InventoryMovement::TYPE_ADJUSTMENT_OUT;

            // Create inventory movement
            $movement = InventoryMovement::create([
                'tenant_id' => $this->tenant_id,
                'store_id' => $this->store_id,
                'inventory_item_id' => $item->inventory_item_id,
                'user_id' => $this->user_id,
                'type' => $movementType,
                'quantity' => abs($item->difference_qty),
                'unit_cost' => $item->unit_cost,
                'total_cost' => $item->total_cost,
                'reason' => $this->reason,
                'reference_type' => InventoryAdjustment::class,
                'reference_id' => $this->id,
                'notes' => "Penyesuaian stok: {$this->adjustment_number} - {$this->notes}",
            ]);

            // Update stock level
            $stockLevel = StockLevel::getOrCreateForInventoryItem($item->inventory_item_id, $this->store_id);
            $stockLevel->updateFromMovement($movement);
        }
    }
}


