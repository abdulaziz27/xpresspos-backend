<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $storeContext = StoreContext::instance();

        $requestedStoreId = $request->query('store')
            ?? $request->query('store_id')
            ?? $request->header('X-Store-Id')
            ?? $request->header('Store-Id');

        if (is_string($requestedStoreId)) {
            $requestedStoreId = trim($requestedStoreId);
        }

        if ($requestedStoreId && $user) {
            if ($user->hasRole('admin_sistem')) {
                $storeContext->set($requestedStoreId);
            } elseif (!$storeContext->setForUser($user, $requestedStoreId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not assigned to the requested store context.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if ($user) {
            $currentStoreId = $storeContext->current($user);

            // If no store context, try primary store assignment
            if (!$currentStoreId) {
                $primaryStore = $user->primaryStore();

                if ($primaryStore instanceof Store) {
                    $storeContext->set($primaryStore->id);
                    $currentStoreId = $primaryStore->id;
                }
            }

            if ($currentStoreId) {
                // Load store relation if not already loaded
                if (!$user->relationLoaded('store') || !$user->store || $user->store->id !== $currentStoreId) {
                    $store = $user->stores()
                        ->where('stores.id', $currentStoreId)
                        ->first();

                    if ($store) {
                        $user->setRelation('store', $store);
                    }
                }
                
                // CRITICAL: Set team context for permissions using tenant_id from store
                if ($store && $store->tenant_id) {
                    setPermissionsTeamId($store->tenant_id);
                } else {
                    // Fallback to user's tenant if store doesn't have tenant_id
                    $tenantId = $user->currentTenantId();
                    if ($tenantId) {
                        setPermissionsTeamId($tenantId);
                    }
                }
            }
        }

        return $next($request);
    }
}
