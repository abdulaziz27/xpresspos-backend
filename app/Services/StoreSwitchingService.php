<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class StoreSwitchingService
{
    /**
     * Switch to a different store context for system admin.
     */
    public function switchStore(User $user, string $storeId): bool
    {
        // Only system admins can switch stores
        if (!$user->hasRole('admin_sistem')) {
            throw new \Exception('Only system administrators can switch store context');
        }

        // Validate store exists
        $store = Store::find($storeId);
        if (!$store) {
            throw new \Exception('Store not found');
        }

        // Log the store switch for audit
        $this->logStoreSwitch($user, $storeId, $user->store_id);

        // Set the store context in session
        Session::put('admin_store_context', $storeId);
        
        // Optionally update user's store_id temporarily
        $user->update(['store_id' => $storeId]);

        return true;
    }

    /**
     * Clear store context and return to global admin view.
     */
    public function clearStoreContext(User $user): bool
    {
        if (!$user->hasRole('admin_sistem')) {
            throw new \Exception('Only system administrators can clear store context');
        }

        $previousStoreId = Session::get('admin_store_context');
        
        // Log the context clear
        $this->logStoreSwitch($user, null, $previousStoreId);

        // Clear session
        Session::forget('admin_store_context');
        
        // Reset user's store_id to null for global access
        $user->update(['store_id' => null]);

        return true;
    }

    /**
     * Get current store context for system admin.
     */
    public function getCurrentStoreContext(User $user): ?string
    {
        if (!$user->hasRole('admin_sistem')) {
            return $user->store_id;
        }

        return Session::get('admin_store_context', $user->store_id);
    }

    /**
     * Get available stores for switching.
     */
    public function getAvailableStores(User $user): array
    {
        if (!$user->hasRole('admin_sistem')) {
            return [];
        }

        return Store::select('id', 'name', 'email', 'status')
                   ->orderBy('name')
                   ->get()
                   ->toArray();
    }

    /**
     * Check if user is currently in a store context.
     */
    public function isInStoreContext(User $user): bool
    {
        if (!$user->hasRole('admin_sistem')) {
            return true; // Regular users are always in their store context
        }

        return Session::has('admin_store_context') || $user->store_id !== null;
    }

    /**
     * Get store information for current context.
     */
    public function getCurrentStoreInfo(User $user): ?array
    {
        $storeId = $this->getCurrentStoreContext($user);
        
        if (!$storeId) {
            return null;
        }

        $store = Store::find($storeId);
        
        return $store ? [
            'id' => $store->id,
            'name' => $store->name,
            'email' => $store->email,
            'status' => $store->status,
        ] : null;
    }

    /**
     * Validate store access for system admin operations.
     */
    public function validateStoreAccess(User $user, string $storeId): bool
    {
        // System admin can access any store
        if ($user->hasRole('admin_sistem')) {
            return true;
        }

        // Regular users can only access their own store
        return $user->store_id === $storeId;
    }

    /**
     * Log store switching activity for audit trail.
     */
    private function logStoreSwitch(User $user, ?string $newStoreId, ?string $previousStoreId): void
    {
        Log::info('System admin store context switch', [
            'admin_user_id' => $user->id,
            'admin_email' => $user->email,
            'previous_store_id' => $previousStoreId,
            'new_store_id' => $newStoreId,
            'action' => $newStoreId ? 'switch_store' : 'clear_context',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        // Create activity log entry if model exists
        if (class_exists(\App\Models\ActivityLog::class)) {
            \App\Models\ActivityLog::create([
                'store_id' => $newStoreId ?? $previousStoreId,
                'user_id' => $user->id,
                'event' => 'admin.store_switch',
                'auditable_type' => 'store_context',
                'auditable_id' => $newStoreId,
                'old_values' => ['store_id' => $previousStoreId],
                'new_values' => ['store_id' => $newStoreId],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}