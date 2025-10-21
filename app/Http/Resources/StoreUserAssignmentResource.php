<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreUserAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'user_id' => $this->user_id,
            'assignment_role' => $this->assignment_role,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relationships
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                    'code' => $this->store->code ?? null,
                    'address' => $this->store->address ?? null,
                ];
            }),
            
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            
            // Computed properties
            'role_display' => $this->getRoleDisplayName(),
            'assignment_status' => $this->getAssignmentStatus(),
        ];
    }
    
    /**
     * Get display name for assignment role.
     */
    protected function getRoleDisplayName(): string
    {
        return $this->assignment_role->getDisplayName();
    }
    
    /**
     * Get assignment status.
     */
    protected function getAssignmentStatus(): string
    {
        if ($this->is_primary) {
            return 'Primary Assignment';
        }
        
        return 'Secondary Assignment';
    }
}