<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantContext = TenantContext::instance();

        // Get tenant from query/header (for API)
        $requestedTenantId = $request->query('tenant')
            ?? $request->query('tenant_id')
            ?? $request->header('X-Tenant-Id')
            ?? $request->header('Tenant-Id');

        if ($requestedTenantId && $user) {
            if ($user->hasRole('admin_sistem')) {
                // Admin can access any tenant
                $tenantContext->set($requestedTenantId);
            } elseif (!$tenantContext->setForUser($user, $requestedTenantId)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TENANT_ACCESS_DENIED',
                        'message' => 'You do not have access to the requested tenant.',
                    ],
                    'meta' => [
                        'timestamp' => now()->toISOString(),
                        'version' => 'v1',
                    ]
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if ($user) {
            $currentTenantId = $tenantContext->current($user);

            // Auto-resolve tenant from store context
            if (!$currentTenantId) {
                $storeContext = \App\Services\StoreContext::instance();
                $storeId = $storeContext->current($user);
                
                if ($storeId) {
                    $store = \App\Models\Store::find($storeId);
                    if ($store?->tenant_id) {
                        $tenantContext->set($store->tenant_id);
                        $currentTenantId = $store->tenant_id;
                    }
                }
            }

            // Fallback: Get from user's primary tenant
            if (!$currentTenantId) {
                $primaryTenant = $user->currentTenant();
                if ($primaryTenant) {
                    $tenantContext->set($primaryTenant->id);
                    $currentTenantId = $primaryTenant->id;
                }
            }

            if ($currentTenantId) {
                // Set tenant_id attribute on user for easy access
                $user->setAttribute('tenant_id', $currentTenantId);
                
                // Set team context for permissions
                setPermissionsTeamId($currentTenantId);
            }
        }

        return $next($request);
    }
}

