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
            'operation_mode' => $this->operation_mode,
            'payment_mode' => $this->payment_mode,
            'subtotal' => $this->subtotal ?? 0,
            'tax_amount' => $this->tax_amount ?? 0,
            'discount_amount' => $this->discount_amount ?? 0,
            'service_charge' => $this->service_charge ?? 0,
            // ✅ CRITICAL: Total amount harus selalu ada, calculate jika null
            'total_amount' => $this->total_amount ?? ($this->subtotal ?? 0) + ($this->tax_amount ?? 0) + ($this->service_charge ?? 0) - ($this->discount_amount ?? 0),
            'total_items' => $this->total_items ?? 0,
            // 'payment_method' => $this->payment_method, // REMOVED: Field tidak ada di database
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
            
            // ✅ CRITICAL: Items harus selalu muncul jika di-eager load
            'items' => $this->relationLoaded('items') 
                ? OrderItemResource::collection($this->items)
                : ($this->whenLoaded('items') ? OrderItemResource::collection($this->items) : []),
            
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
                return $this->isFullyPaid();
            }),
            'payment_status' => $this->whenLoaded('payments', function () {
                return $this->getPaymentStatus();
            }),
            'payment_status_display' => $this->whenLoaded('payments', function () {
                return $this->getPaymentStatusDisplay();
            }),
            'remaining_balance' => $this->whenLoaded('payments', function () {
                return $this->getRemainingBalance();
            }),
        ];
    }
}