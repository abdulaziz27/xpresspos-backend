<?php

namespace App\Http\Middleware;

use App\Services\StoreContext;
use App\Services\StorePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStorePermission
{
    public function __construct(
        private readonly StorePermissionService $permissionService,
        private readonly StoreContext $storeContext
    ) {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Get current store context
        $storeId = $this->storeContext->current($user);
        
        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'No store context available'
            ], 403);
        }

        // Check permission
        if (!$this->permissionService->hasPermission($user, $storeId, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions for this action'
            ], 403);
        }

        return $next($request);
    }
}