<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'service_charge' => $this->service_charge,
            'total_amount' => $this->total_amount,
            'total_items' => $this->total_items,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            
            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            
            'member' => $this->whenLoaded('member', function () {
                return new MemberResource($this->member);
            }),
            
            'table' => $this->whenLoaded('table', function () {
                return new TableResource($this->table);
            }),
            
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            
            // Counts
            'items_count' => $this->whenCounted('items'),
            'payments_count' => $this->whenCounted('payments'),
            'refunds_count' => $this->whenCounted('refunds'),
            
            // Computed attributes
            'can_be_modified' => $this->canBeModified(),
            'is_completed' => $this->status === 'completed',
            'is_paid' => $this->whenLoaded('payments', function () {
                return $this->payments->sum('amount') >= $this->total_amount;
            }),
        ];
    }
}