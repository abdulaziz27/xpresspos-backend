<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Models\Scopes\StoreScope;
use App\Services\StoreContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    /**
     * The "booted" method of the model.
     */
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);
        
        // Automatically set store_id when creating
        static::creating(function ($model) {
            if (!$model->store_id && auth()->check()) {
                $user = auth()->user();

                // System admin needs to explicitly set store_id
                if ($user->hasRole('admin_sistem')) {
                    if (!$model->store_id) {
                        throw new \Exception('System admin must explicitly set store_id when creating records');
                    }
                } else {
                    $storeId = StoreContext::instance()->current($user);

                    if (!$storeId) {
                        throw new \Exception('Tidak dapat menentukan store aktif untuk pengguna.');
                    }

                    $model->store_id = $storeId;
                }
            }
        });
    }

    /**
     * Get the store that owns the model.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if the model belongs to the specified store.
     */
    public function belongsToStore(string $storeId): bool
    {
        return $this->store_id === $storeId;
    }

    /**
     * Check if the model belongs to the current user's store.
     */
    public function belongsToCurrentUserStore(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $storeId = StoreContext::instance()->current($user);

        return $storeId ? $this->belongsToStore($storeId) : false;
    }

    /**
     * Scope to get models for a specific store.
     */
    public function scopeForStore($query, string $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}
