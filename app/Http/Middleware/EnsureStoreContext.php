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
            ?? $request->query('store_id');

        if ($requestedStoreId && $user) {
            $storeContext->setForUser($user, $requestedStoreId);
        }

        if ($user) {
            $currentStoreId = $storeContext->current($user);

            // If no store context, try to get from user's store_id first
            if (!$currentStoreId && $user->store_id) {
                $currentStoreId = $user->store_id;
                $storeContext->set($currentStoreId);
            }

            // If still no store context, try primary store assignment
            if (!$currentStoreId) {
                $primaryStore = $user->primaryStore();

                if ($primaryStore instanceof Store) {
                    $storeContext->set($primaryStore->id);
                    $currentStoreId = $primaryStore->id;
                }
            }

            if ($currentStoreId) {
                // Ensure user has the current store_id attribute
                $user->setAttribute('store_id', $currentStoreId);

                // Load store relation if not already loaded
                if (!$user->relationLoaded('store') || !$user->store || $user->store->id !== $currentStoreId) {
                    $store = $user->stores()
                        ->where('stores.id', $currentStoreId)
                        ->first();

                    if ($store) {
                        $user->setRelation('store', $store);
                    }
                }
                
                // CRITICAL: Set team context for permissions
                setPermissionsTeamId($currentStoreId);
            }
        }

        return $next($request);
    }
}
