<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_orders' => $this->collection->count(),
                'total_revenue' => $this->collection->where('status', 'completed')->sum('total_amount'),
                'average_order_value' => $this->collection->where('status', 'completed')->avg('total_amount') ?? 0,
                'status_breakdown' => [
                    'draft' => $this->collection->where('status', 'draft')->count(),
                    'open' => $this->collection->where('status', 'open')->count(),
                    'completed' => $this->collection->where('status', 'completed')->count(),
                ],
            ],
        ];
    }
}