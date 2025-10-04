<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
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
            'table_number' => $this->table_number,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'location' => $this->location,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Enhanced fields
            'occupied_at' => $this->occupied_at?->toISOString(),
            'last_cleared_at' => $this->last_cleared_at?->toISOString(),
            'total_occupancy_count' => $this->total_occupancy_count,
            'average_occupancy_duration' => $this->average_occupancy_duration,
            'notes' => $this->notes,

            // Relationships
            'current_order' => $this->whenLoaded('currentOrder', function () {
                return $this->currentOrder ? [
                    'id' => $this->currentOrder->id,
                    'order_number' => $this->currentOrder->order_number,
                    'status' => $this->currentOrder->status,
                    'total_amount' => $this->currentOrder->total_amount,
                    'created_at' => $this->currentOrder->created_at?->toISOString(),
                ] : null;
            }),
            'current_occupancy' => $this->whenLoaded('currentOccupancy', function () {
                $occupancy = $this->currentOccupancy();
                return $occupancy ? [
                    'id' => $occupancy->id,
                    'occupied_at' => $occupancy->occupied_at?->toISOString(),
                    'party_size' => $occupancy->party_size,
                    'duration_minutes' => $occupancy->occupied_at ? $occupancy->occupied_at->diffInMinutes(now()) : 0,
                    'formatted_duration' => $occupancy->occupied_at ? $this->formatDuration($occupancy->occupied_at->diffInMinutes(now())) : 'N/A',
                    'notes' => $occupancy->notes,
                ] : null;
            }),
            
            // Computed attributes
            'is_available' => $this->isAvailable(),
            'is_occupied' => $this->isOccupied(),
            'can_be_occupied' => $this->isAvailable(),
            'current_occupancy_duration' => $this->getCurrentOccupancyDuration(),
            'is_occupied_too_long' => $this->isOccupiedTooLong(),
            'formatted_average_duration' => $this->getFormattedAverageDuration(),
        ];
    }
    
    /**
     * Get display name for status.
     */
    private function getStatusDisplay(): string
    {
        return match ($this->status) {
            'available' => 'Available',
            'occupied' => 'Occupied',
            'reserved' => 'Reserved',
            'maintenance' => 'Under Maintenance',
            default => ucfirst($this->status),
        };
    }

    /**
     * Format duration in minutes to human readable format.
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Get formatted average duration.
     */
    private function getFormattedAverageDuration(): string
    {
        if (!$this->average_occupancy_duration) {
            return 'N/A';
        }

        return $this->formatDuration((int) $this->average_occupancy_duration);
    }
}