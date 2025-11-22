<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class TenantScope implements Scope
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

        // Apply tenant scoping for all other users
        $tenantId = $user?->currentTenant()?->id;

        if ($user && $tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        } else {
            // If no authenticated user or no tenant_id, restrict to no results
            // This prevents data leakage in edge cases
            $builder->whereRaw('1 = 0');

            // Log potential security issue
            if ($user && !$tenantId) {
                Log::warning('User without tenant_id attempted to access scoped model', [
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
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}

