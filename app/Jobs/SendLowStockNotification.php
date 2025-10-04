<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Notifications\LowStockAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendLowStockNotification implements ShouldQueue
{
    use Queueable;

    protected Product $product;
    protected StockLevel $stockLevel;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product, StockLevel $stockLevel)
    {
        $this->product = $product;
        $this->stockLevel = $stockLevel;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get store owners and managers who should be notified
        $usersToNotify = User::where('store_id', $this->product->store_id)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['owner', 'manager']);
            })
            ->get();

        // Send notification to each user
        Notification::send($usersToNotify, new LowStockAlert($this->product, $this->stockLevel));
    }
}
