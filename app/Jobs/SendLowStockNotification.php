<?php

namespace App\Jobs;

use App\Models\InventoryItem;
use App\Models\StockLevel;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotification implements ShouldQueue
{
    use Queueable;

    protected InventoryItem $inventoryItem;
    protected StockLevel $stockLevel;

    /**
     * Create a new job instance.
     * 
     * NOTE: Now accepts InventoryItem (not Product). Stock is tracked per inventory_item.
     */
    public function __construct(InventoryItem $inventoryItem, StockLevel $stockLevel)
    {
        $this->inventoryItem = $inventoryItem;
        $this->stockLevel = $stockLevel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get store owners and managers who should be notified
        $usersToNotify = User::where('store_id', $this->stockLevel->store_id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['owner', 'manager']);
            })
            ->get();

        // Send notification to each user
        Notification::send($usersToNotify, new LowStockAlert($this->inventoryItem, $this->stockLevel));
    }
}
