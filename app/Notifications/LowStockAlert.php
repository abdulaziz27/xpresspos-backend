<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use App\Models\StockLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected InventoryItem $inventoryItem;
    protected StockLevel $stockLevel;

    /**
     * Create a new notification instance.
     * 
     * NOTE: Now accepts InventoryItem (not Product). Stock is tracked per inventory_item.
     */
    public function __construct(InventoryItem $inventoryItem, StockLevel $stockLevel)
    {
        $this->inventoryItem = $inventoryItem;
        $this->stockLevel = $stockLevel;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $minLevel = $this->stockLevel->min_stock_level > 0
            ? $this->stockLevel->min_stock_level
            : ($this->inventoryItem->min_stock_level ?? 0);

        return (new MailMessage)
            ->subject('Low Stock Alert - ' . $this->inventoryItem->name)
            ->greeting('Low Stock Alert!')
            ->line("The inventory item '{$this->inventoryItem->name}' (SKU: {$this->inventoryItem->sku}) is running low on stock.")
            ->line("Current stock: {$this->stockLevel->current_stock}")
            ->line("Minimum level: {$minLevel}")
            ->line("Available stock: {$this->stockLevel->available_stock}")
            ->action('View Inventory', url('/admin/inventory'))
            ->line('Please restock this item to avoid stockouts.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $minLevel = $this->stockLevel->min_stock_level > 0
            ? $this->stockLevel->min_stock_level
            : ($this->inventoryItem->min_stock_level ?? 0);

        return [
            'type' => 'low_stock_alert',
            'inventory_item_id' => $this->inventoryItem->id,
            'inventory_item_name' => $this->inventoryItem->name,
            'inventory_item_sku' => $this->inventoryItem->sku,
            'current_stock' => $this->stockLevel->current_stock,
            'min_stock_level' => $minLevel,
            'available_stock' => $this->stockLevel->available_stock,
            'message' => "Low stock alert for {$this->inventoryItem->name}",
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
