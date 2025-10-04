<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
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
            'member_number' => $this->member_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'address' => $this->address,
            'loyalty_points' => $this->loyalty_points,
            'formatted_loyalty_points' => number_format($this->loyalty_points),
            'total_spent' => $this->total_spent,
            'formatted_total_spent' => $this->getFormattedTotalSpent(),
            'visit_count' => $this->visit_count,
            'last_visit_at' => $this->last_visit_at?->toISOString(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'tier' => $this->whenLoaded('tier', function () {
                return $this->tier ? [
                    'id' => $this->tier->id,
                    'name' => $this->tier->name,
                    'slug' => $this->tier->slug,
                    'color' => $this->tier->color,
                    'discount_percentage' => $this->tier->discount_percentage,
                    'benefits' => $this->tier->benefits,
                    'description' => $this->tier->description,
                ] : null;
            }),
            'recent_orders' => $this->whenLoaded('orders', function () {
                return $this->orders->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'created_at' => $order->created_at?->toISOString(),
                    ];
                });
            }),
            
            // Computed attributes
            'current_tier_name' => $this->tier?->name ?? $this->getLoyaltyTier(),
            'tier_discount_percentage' => $this->getTierDiscountPercentage(),
            'points_to_next_tier' => $this->getPointsToNextTier(),
            'average_order_value' => $this->getAverageOrderValue(),
            'days_since_last_visit' => $this->getDaysSinceLastVisit(),
        ];
    }
    
    /**
     * Get formatted total spent for display.
     */
    private function getFormattedTotalSpent(): string
    {
        return number_format($this->total_spent, 0, ',', '.');
    }
    
    /**
     * Get loyalty tier based on points.
     */
    private function getLoyaltyTier(): string
    {
        return match (true) {
            $this->loyalty_points >= 10000 => 'Platinum',
            $this->loyalty_points >= 5000 => 'Gold',
            $this->loyalty_points >= 1000 => 'Silver',
            default => 'Bronze',
        };
    }
    
    /**
     * Get points needed for next tier.
     */
    private function getPointsToNextTier(): int
    {
        return match (true) {
            $this->loyalty_points >= 10000 => 0,
            $this->loyalty_points >= 5000 => 10000 - $this->loyalty_points,
            $this->loyalty_points >= 1000 => 5000 - $this->loyalty_points,
            default => 1000 - $this->loyalty_points,
        };
    }
    
    /**
     * Get average order value.
     */
    private function getAverageOrderValue(): float
    {
        if ($this->visit_count > 0 && $this->total_spent > 0) {
            return round($this->total_spent / $this->visit_count, 2);
        }
        return 0;
    }
    
    /**
     * Get days since last visit.
     */
    private function getDaysSinceLastVisit(): ?int
    {
        if ($this->last_visit_at) {
            return $this->last_visit_at->diffInDays(now());
        }
        return null;
    }
}