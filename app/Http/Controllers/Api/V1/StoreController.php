<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware([
            'auth:sanctum',
            \App\Http\Middleware\EnsureStoreContext::class,
        ]);
    }

    /**
     * Return the authenticated user's current store with full settings payload.
     */
    public function current(Request $request): JsonResponse
    {
        // Check viewAny permission for stores
        $this->authorize('viewAny', Store::class);

        $user = $request->user();
        $storeContext = StoreContext::instance();

        // Resolve the target store id from context or the user's default store.
        $storeId = $storeContext->current($user) ?? $user->store_id;

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_CONTEXT_NOT_SET',
                    'message' => 'No active store context found for the current user.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                ],
            ], 404);
        }

        $store = Store::find($storeId);

        if (!$store) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_NOT_FOUND',
                    'message' => 'The requested store could not be found.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                ],
            ], 404);
        }

        // Ensure the user actually belongs to this store.
        $hasAccess = $user->store_id === $storeId
            || $user->stores()->where('stores.id', $storeId)->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'STORE_ACCESS_DENIED',
                    'message' => 'You do not have permission to access this store.',
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => 'v1',
                ],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new StoreResource($store),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => 'v1',
            ],
        ]);
    }
}
