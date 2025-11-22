<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
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
            'amount' => $this->amount,
            'formatted_amount' => $this->getFormattedAmount(),
            'reason' => $this->reason,
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'notes' => $this->notes,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'total_amount' => $this->order->total_amount,
                    'status' => $this->order->status,
                ];
            }),
            
            'payment' => $this->whenLoaded('payment', function () {
                return [
                    'id' => $this->payment->id,
                    'payment_method' => $this->payment->payment_method,
                    'amount' => $this->payment->amount,
                    'reference_number' => $this->payment->reference_number,
                ];
            }),
            
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            
            'approved_by' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ];
            }),
            
            // Computed attributes
            'is_completed' => $this->status === 'processed',
            'is_approved' => $this->status === 'approved',
            'is_processed' => $this->status === 'processed',
            'can_be_modified' => $this->canBeModified(),
        ];
    }
    
    /**
     * Get formatted amount for display.
     */
    private function getFormattedAmount(): string
    {
        return number_format($this->amount, 0, ',', '.');
    }
    
    /**
     * Get display name for status.
     */
    private function getStatusDisplay(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'processed' => 'Processed',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }
}