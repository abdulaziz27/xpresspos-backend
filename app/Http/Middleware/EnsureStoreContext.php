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

            if (!$currentStoreId) {
                $primaryStore = $user->primaryStore();

                if ($primaryStore instanceof Store) {
                    $storeContext->set($primaryStore->id);
                    $currentStoreId = $primaryStore->id;
                }
            }

            if ($currentStoreId) {
                $user->setAttribute('store_id', $currentStoreId);

                $store = $user->stores()
                    ->where('stores.id', $currentStoreId)
                    ->first();

                if ($store) {
                    $user->setRelation('store', $store);
                }
                
                // Set team context for permissions
                setPermissionsTeamId($currentStoreId);
            }
        }

        return $next($request);
    }
}
