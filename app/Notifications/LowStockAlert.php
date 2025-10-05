<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected Product $product;
    protected StockLevel $stockLevel;

    /**
     * Create a new notification instance.
     */
    public function __construct(Product $product, StockLevel $stockLevel)
    {
        $this->product = $product;
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
        return (new MailMessage)
            ->subject('Low Stock Alert - ' . $this->product->name)
            ->greeting('Low Stock Alert!')
            ->line("The product '{$this->product->name}' (SKU: {$this->product->sku}) is running low on stock.")
            ->line("Current stock: {$this->stockLevel->current_stock}")
            ->line("Minimum level: {$this->product->min_stock_level}")
            ->line("Available stock: {$this->stockLevel->available_stock}")
            ->action('View Inventory', url('/admin/inventory'))
            ->line('Please restock this item to avoid stockouts.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'low_stock_alert',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'current_stock' => $this->stockLevel->current_stock,
            'min_stock_level' => $this->product->min_stock_level,
            'available_stock' => $this->stockLevel->available_stock,
            'message' => "Low stock alert for {$this->product->name}",
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
