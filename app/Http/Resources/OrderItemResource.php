<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'product_options' => $this->product_options,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'sku' => $this->product->sku,
                    'image' => $this->product->image,
                    'track_inventory' => $this->product->track_inventory,
                    'stock' => $this->product->stock,
                ];
            }),
            
            // Computed attributes
            'formatted_options' => $this->when($this->product_options, function () {
                return collect($this->product_options)->map(function ($option) {
                    return [
                        'name' => $option['name'] ?? '',
                        'value' => $option['value'] ?? '',
                        'price_adjustment' => $option['price_adjustment'] ?? 0,
                        'formatted_adjustment' => $this->formatPriceAdjustment($option['price_adjustment'] ?? 0),
                    ];
                });
            }),
            
            'line_total' => $this->calculateTotalPrice(),
        ];
    }
    
    /**
     * Format price adjustment for display.
     */
    private function formatPriceAdjustment(float $adjustment): string
    {
        if ($adjustment == 0) {
            return '';
        }
        
        $sign = $adjustment > 0 ? '+' : '';
        return $sign . number_format($adjustment, 0, ',', '.');
    }
}