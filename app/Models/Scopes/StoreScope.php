<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class StoreScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Try to get user from different guards (sanctum for API, web for web)
        $user = auth()->user() ?: request()->user();

        // System admin bypasses tenant scoping
        if ($user && $user->hasRole('admin_sistem')) {
            return;
        }

        // Apply store scoping for all other users
        if ($user && $user->store_id) {
            $builder->where($model->getTable() . '.store_id', $user->store_id);
        } else {
            // If no authenticated user or no store_id, restrict to no results
            // This prevents data leakage in edge cases
            $builder->whereRaw('1 = 0');

            // Log potential security issue
            if ($user && !$user->store_id) {
                Log::warning('User without store_id attempted to access scoped model', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'model' => get_class($model),
                    'timestamp' => now()->toISOString(),
                ]);
            }
        }
    }

    /**
     * Extend the query builder with methods to bypass the scope.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutStoreScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forStore', function (Builder $builder, string $storeId) {
            return $builder->withoutGlobalScope($this)
                ->where($builder->getModel()->getTable() . '.store_id', $storeId);
        });

        $builder->macro('forAllStores', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
